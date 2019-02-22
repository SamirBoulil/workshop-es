<?php

declare(strict_types=1);

namespace Assignements;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author    Samir Boulil <samir.boulil@akeneo.com>
 * @copyright 2019 Akeneo SAS (http://www.akeneo.com)
 */
class SearchSolutions extends TestCase
{
    private const INDEX_NAME = 'movies';

    /** @var Client */
    private $esClient;

    const INDEX_TYPE = 'my_movies';

    public function setUp(): void
    {
        $this->esClient = $this->createMovieIndex();
        $this->indexMovies();
    }

    /**
     * @test
     * @find the movies released in 2009.
     */
    public function exercise1()
    {
        $searchResult = $this->esClient->search([
            'index' => self::INDEX_NAME,
            'type'  => self::INDEX_TYPE,
            'body'  => [
                '_source' => 'title',
                'query'   => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'filter' => [
                                    'term' => [
                                        'release_year' => 2009,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertMovies(['VIRGIN DAISY', 'CROWDS TELEMARK'], $searchResult);
    }

    /**
     * @test
     * @find the movies rated "A" or "B".
     */
    public function exercise2()
    {
        $searchResult = $this->esClient->search([
            'index' => self::INDEX_NAME,
            'type'  => self::INDEX_TYPE,
            'body'  => [
                '_source' => 'title',
                'query'   => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'filter' => [
                                    'terms' => [
                                        'rating' => ['A', 'B'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertMovies(['LADYBUGS ARMAGEDDON', 'ALABAMA DEVIL', 'VAMPIRE WHALE'], $searchResult);
    }

    /**
     * @test
     * @find the movies that have a length between 100 and 130 minutes
     */
    public function exercise3()
    {
        $searchResult = $this->esClient->search([
            'index' => self::INDEX_NAME,
            'type'  => self::INDEX_TYPE,
            'body'  => [
                '_source' => 'title',
                'query'   => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'filter' => [
                                    'range' => [
                                        'length' => [
                                            'gt' => 100,
                                            'lt' => 130,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertMovies(
            [
                'LADYBUGS ARMAGEDDON',
                'ALABAMA DEVIL',
                'VAMPIRE WHALE',
                'CROWDS TELEMARK',
            ],
            $searchResult
        );
    }

    /**
     * @test
     * @find the movies which don't have a "movie_director"
     */
    public function exercise4()
    {
        $searchResult = $this->esClient->search([
            'index' => self::INDEX_NAME,
            'type'  => self::INDEX_TYPE,
            'body'  => [
                '_source' => 'title',
                'query'   => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'must_not' => [
                                    'exists' => [
                                        'field' => 'movie_director',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertMovies(['LADYBUGS ARMAGEDDON', 'ALABAMA DEVIL'], $searchResult);
    }

    /**
     * @test
     * @find the movies that have "mad scientist" in their description but that have not not
     * "Trailers" as special features.
     */
    public function exercise5()
    {
        $searchResult = $this->esClient->search([
            'index' => self::INDEX_NAME,
            'type'  => self::INDEX_TYPE,
            'body'  => [
                '_source' => 'title',
                'query'   => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'filter'   => [
                                    'query_string' => [
                                        'default_field' => 'description',
                                        'query'         => '*mad* AND *scientist*',
                                    ],
                                ],
                                'must_not' => [
                                    'term' => [
                                        'special_features' => 'Trailers',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertMovies(['EXORCIST STING', 'LADYBUGS ARMAGEDDON'], $searchResult);
    }

    /**
     * @test
     * @find the "French" and "Spanish" movies having a length <= 126 minutes
     */
    public function exercise6()
    {
        $searchResult = $this->esClient->search([
            'index' => self::INDEX_NAME,
            'type'  => self::INDEX_TYPE,
            'body'  => [
                '_source' => 'title',
                'query'   => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'filter' => [
                                    'bool' => [
                                        'should' => [
                                            [
                                                'term' => [
                                                    'language' => 'French',
                                                ],
                                            ],
                                            [
                                                'term' => [
                                                    'language' => 'Spanish',
                                                ],
                                            ],
                                        ],
                                        'filter' => [
                                            [
                                                'range' => [
                                                    'length' => [
                                                        'lte' => 126,
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertMovies(['ALABAMA DEVIL', 'VAMPIRE WHALE'], $searchResult);
    }

    // Question: Given those search usecases, what would you do regarding the mapping and the dataset ?

    private function createMovieIndex(): Client
    {
        $esClient = ClientBuilder::create()->build();
        if ($esClient->indices()->exists(['index' => self::INDEX_NAME])) {
            $esClient->indices()->delete(['index' => self::INDEX_NAME]);
        }
        $esClient->indices()->create([
            'index' => self::INDEX_NAME,
            'body'  => [
                'settings' => [
                    'analysis' => [
                        'normalizer' => [
                            'lowercase_normalizer' => [
                                'filter' => [
                                    'lowercase',
                                ],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    self::INDEX_TYPE => [
                        'properties' => [
                            'film_id'          => [
                                'type' => 'integer',
                            ],
                            'title'            => [
                                'type' => 'keyword',
                            ],
                            'description'      => [
                                'type'       => 'keyword',
                                'normalizer' => 'lowercase_normalizer',
                            ],
                            'release_year'     => [
                                'type' => 'integer',
                            ],
                            'language'         => [
                                'type' => 'keyword',
                            ],
                            'rental_duration'  => [
                                'type' => 'integer',
                            ],
                            'rental_rate'      => [
                                'type' => 'float',
                            ],
                            'length'           => [
                                'type' => 'integer',
                            ],
                            'replacement_cost' => [
                                'type' => 'float',
                            ],
                            'rating'           => [
                                'type' => 'keyword',
                            ],
                            'last_update'      => [
                                'type' => 'date',
                            ],
                            'special_features' => [
                                'type' => 'keyword',
                            ],
                            'movie_director'   => [
                                'type'       => 'keyword',
                                'normalizer' => 'lowercase_normalizer',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return $esClient;
    }

    private function indexMovies(): void
    {
        $movies = json_decode(file_get_contents('movies.json'), true);
        $bulk = [];
        foreach ($movies as $movie) {
            $bulk['body'][] = [
                'index' => [
                    '_index' => self::INDEX_NAME,
                    '_type'  => self::INDEX_TYPE,
                    '_id'    => $movie['film_id'],
                ],
            ];
            $bulk['body'][] = $movie;
        }
        $this->esClient->bulk($bulk);
        $this->esClient->indices()->refresh(['index' => self::INDEX_NAME]);
    }

    private function assertMovies(array $expectedTitles, array $searchResult): void
    {
        $this->assertNotEmpty($searchResult['hits']['hits'], 'Search result not expected to be empty');

        $actualTitles = $this->getMovieTitles($searchResult);
        sort($actualTitles);
        sort($expectedTitles);

        $this->assertEquals($expectedTitles, $actualTitles);
    }

    /**
     * @param array $searchResult
     *
     * @return array
     *
     */
    private function getMovieTitles(array $searchResult): array
    {
        $actualTitles = array_map(
            function (array $searchResult) {
                return $searchResult['_source']['title'];
            },
            $searchResult['hits']['hits']
        );

        return $actualTitles;
    }
}
