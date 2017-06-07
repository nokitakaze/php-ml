<?php

declare(strict_types=1);

namespace tests\Classification;

use Phpml\Classification\DecisionTree;
use Phpml\ModelManager;
use PHPUnit\Framework\TestCase;

class DecisionTreeTest extends TestCase
{
    private $data = [
        ['sunny',        85,        85,    'false',    'Dont_play'    ],
        ['sunny',        80,    90,    'true',        'Dont_play'    ],
        ['overcast',    83,    78,    'false',    'Play'        ],
        ['rain',        70,    96,    'false',    'Play'        ],
        ['rain',        68,    80,    'false',    'Play'        ],
        ['rain',        65,    70,    'true',    'Dont_play'    ],
        ['overcast',    64,    65,    'true',    'Play'        ],
        ['sunny',        72,    95,    'false',    'Dont_play'    ],
        ['sunny',        69,    70,    'false',    'Play'        ],
        ['rain',        75,    80,    'false',    'Play'        ],
        ['sunny',        75,    70,    'true',    'Play'        ],
        ['overcast',    72,    90,    'true',    'Play'        ],
        ['overcast',    81,    75,    'false',    'Play'        ],
        ['rain',        71,    80,    'true',    'Dont_play'    ]
    ];

    private $extraData = [
        ['scorching',   90,     95,     'false',   'Dont_play'],
        ['scorching',  100,     93,     'true',    'Dont_play'],
    ];

    private function getData($input)
    {
        $targets = array_column($input, 4);
        array_walk($input, function (&$v) {
            array_splice($v, 4, 1);
        });
        return [$input, $targets];
    }

    public function dataPredictSingleSample()
    {
        return [[false], [true]];
    }

    /**
     * @param boolean $add_null_column
     *
     * @return DecisionTree
     *
     * @dataProvider dataPredictSingleSample
     */
    public function testPredictSingleSample($add_null_column)
    {
        list($data, $targets) = $this->getData($this->data);
        $classifier = new DecisionTree(5);
        if ($add_null_column) {
            foreach ($data as &$datum) {
                $datum[] = null;
            }
        }
        $classifier->train($data, $targets);
        $this->assertEquals('Dont_play', $classifier->predict(['sunny', 78, 72, 'false']));
        $this->assertEquals('Play', $classifier->predict(['overcast', 60, 60, 'false']));
        $this->assertEquals('Dont_play', $classifier->predict(['rain', 60, 60, 'true']));

        list($data, $targets) = $this->getData($this->extraData);
        $classifier->train($data, $targets);
        $this->assertEquals('Dont_play', $classifier->predict(['scorching', 95, 90, 'true']));
        $this->assertEquals('Play', $classifier->predict(['overcast', 60, 60, 'false']));
        return $classifier;
    }

    public function testSaveAndRestore()
    {
        list($data, $targets) = $this->getData($this->data);
        $classifier = new DecisionTree(5);
        $classifier->train($data, $targets);

        $testSamples = [['sunny', 78, 72, 'false'], ['overcast', 60, 60, 'false']];
        $predicted = $classifier->predict($testSamples);

        $filename = 'decision-tree-test-'.rand(100, 999).'-'.uniqid();
        $filepath = tempnam(sys_get_temp_dir(), $filename);
        $modelManager = new ModelManager();
        $modelManager->saveToFile($classifier, $filepath);

        $restoredClassifier = $modelManager->restoreFromFile($filepath);
        $this->assertEquals($classifier, $restoredClassifier);
        $this->assertEquals($predicted, $restoredClassifier->predict($testSamples));
    }

    public function testTreeDepth()
    {
        list($data, $targets) = $this->getData($this->data);
        $classifier = new DecisionTree(5);
        $classifier->train($data, $targets);
        $this->assertLessThanOrEqual(5, $classifier->actualDepth);
    }

    function dataIsCategoricalColumn()
    {
        $data = [];
        $data[] = [
            ['dic1', 1, 2, 3, 100, 10000],
            true,
        ];
        $data[] = [
            [1.5, 1, 2, 3, 100, 10000],
            false,
        ];
        $data[] = [
            ['dic1', 1, 2, 3, 100, 10000, null],
            true,
        ];
        $data[] = [
            [1.5, 1, 2, 3, 100, 10000, null],
            false,
        ];
        $data[] = [
            [1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3,
             100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100,
             10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000,
             1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3,
             100, 10000, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null,],
            true,
        ];
        $data[] = [
            [1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3,
             100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100,
             10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000,
             1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3, 100, 10000, 1, 2, 3,
             100, 10000],
            true,
        ];

        return $data;
    }

    /**
     * @param array   $value
     * @param boolean $expected
     *
     * @dataProvider dataIsCategoricalColumn
     */
    function testIsCategoricalColumn(array $value, $expected)
    {
        $reflection = new \ReflectionMethod('\\Phpml\\Classification\\DecisionTree', 'isCategoricalColumn');
        $reflection->setAccessible(true);
        $this->assertEquals($expected, $reflection->invoke(null, $value));
    }

    function testGetterSetterTypes()
    {
        $data = [
            [0, 1, 2, 3, 4, 5, 6],
            [0, 1, 2, 3, 4, 5, 6],
            ['dic1', 1, 2, 3, 4, 5, 6],
            ['dic1', 1, 2, 3, null, 5, 6,],
        ];
        $new_data = [];
        for ($j = 0; $j < count($data[0]); $j++) {
            $new_data[] = array_fill(0, count($data), 0);
        }
        for ($i = 0; $i < count($data); $i++) {
            for ($j = 0; $j < count($data[0]); $j++) {
                $new_data[$j][$i] = $data[$i][$j];
            }
        }
        unset($i, $j, $data);
        $types = [
            DecisionTree::CONTINUOUS,
            DecisionTree::NOMINAL,
            DecisionTree::NOMINAL,
            DecisionTree::NOMINAL,
        ];
        $types_input = [
            DecisionTree::CONTINUOUS,
            DecisionTree::NOMINAL,
            null,
            DecisionTree::NOMINAL,
        ];
        $classifier = new DecisionTree();
        $classifier->setInstanceColumnTypes($types_input);

        $actual = $classifier->getInstanceColumnTypes();
        $this->assertEquals($types_input, $actual);

        $classifier->train($new_data, range(1, count($new_data)));
        $actual = $classifier->getInstanceColumnTypes();
        $this->assertEquals($types, $actual);
    }
}
