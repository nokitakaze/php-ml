<?php

declare(strict_types=1);

namespace tests\suites\Classification;

use Phpml\Classification\DecisionTree;
use PHPUnit\Framework\TestCase;

class DecisionTreeTest extends TestCase
{
    /**
     * @param array   $rows
     * @param integer $output_dictionary_length
     * @param integer $max_depth
     *
     * @return \Closure
     */
    protected function generateBranch(&$rows, $output_dictionary_length, $max_depth = 100)
    {
        $row_num = mt_rand(0, count($rows));
        if ((mt_rand(0, 2) == 0) and ($max_depth > 0)) {
            $sub_closure_s = $this->generateBranch($rows, $output_dictionary_length, $max_depth - 1);
        } else {
            $ret_value = mt_rand(0, $output_dictionary_length - 1);
            $sub_closure_s = function ($input, $value) use ($ret_value) {
                return ['type' => 1, 'value' => $ret_value];
            };
        }
        if ((mt_rand(0, 2) > 0) and ($max_depth > 0)) {
            $sub_closure_f = $this->generateBranch($rows, $output_dictionary_length, $max_depth - 1);
        } else {
            $ret_value = mt_rand(0, $output_dictionary_length - 1);
            $sub_closure_f = function ($input, $value) use ($ret_value) {
                return ['type' => 1, 'value' => $ret_value];
            };
        }

        if ($row_num === count($rows)) {
            $sub_value = mt_rand(-10000, 10000) * 0.0001;
            $closure = function ($input, $value) use (&$rows, $sub_value, $sub_closure_s, $sub_closure_f) {
                if ($value > $sub_value) {
                    return $sub_closure_s($input, $value);
                } else {
                    return $sub_closure_f($input, $value);
                }
            };
        } elseif ($rows[$row_num][0] == DecisionTree::CONTINUOUS) {
            $sub_value = mt_rand(-10000, 10000) * 0.0001;
            $closure = function ($input, $value) use (&$rows, $row_num, $sub_value, $sub_closure_s, $sub_closure_f) {
                if (is_null($input[$row_num])) {
                    return ['type' => 0, 'value' => $value];
                }
                if ($input[$row_num] > $sub_value) {
                    return $sub_closure_s($input, $value);
                } else {
                    return $sub_closure_f($input, $value);
                }
            };
        } else {
            $sub_value = mt_rand(0, count($rows[$row_num][1]) - 1);
            $closure = function ($input, $value) use (&$rows, $row_num, $sub_value, $sub_closure_s, $sub_closure_f) {
                if (is_null($input[$row_num])) {
                    return ['type' => 0, 'value' => $value];
                }
                if ($input[$row_num] == $sub_value) {
                    return $sub_closure_s($input, $value);
                } else {
                    return $sub_closure_f($input, $value);
                }
            };
        }

        return $closure;
    }

    /**
     * @return array
     *
     * Generating some testing dense data. Only 5% of input fields will be unknown
     * This is a system where most of features is known i.e. classification of images or text
     */
    protected function generateSingleSampleForMultiple()
    {
        $data = [];
        $input_length = mt_rand(2, 4);

        $rows = [];
        for ($i = 0; $i < $input_length; $i++) {
            if (mt_rand(0, 1) == 0) {
                // DecisionTree::CONTINUOUS
                if (mt_rand(0, 3) == 0) {
                    $min = 0;
                    $max = 1;
                } else {
                    $step = 1 / mt_rand(1, 10);
                    $min = $step * mt_rand(-10, 10);
                    $max = $min + $step * mt_rand(1, 10);
                }
                $rows[] = [DecisionTree::CONTINUOUS, $min, $max];
            } else {
                //DecisionTree::NOMINAL
                $dictionary_length = mt_rand(2, 7);
                $dictionary = range(1, $dictionary_length);
                if (mt_rand(0, 3) == 0) {
                    $step = mt_rand(1, 10);
                    array_walk($dictionary, function (&$a) use ($step) {
                        $a *= $step;
                    });
                } else {
                    array_walk($dictionary, function (&$a) {
                        $a = 'dic'.$a;
                    });
                }
                shuffle($dictionary);
                $rows[] = [DecisionTree::NOMINAL, $dictionary];
            }
        }

        // Generate values for output field: dic0, dic1, dic2,.. and then shuffle it
        $output_dictionary = range(0, mt_rand(2, 10));
        array_walk($output_dictionary, function (&$a) {
            $a = 'dic'.$a;
        });
        shuffle($output_dictionary);

        /**
         * @var \Closure[] $scenario
         */
        $scenario = [];
        $scenario_length = $input_length * mt_rand(3, 5);
        for ($i = 0; $i < $scenario_length; $i++) {
            $r = mt_rand(0, 9);
            switch ($r) {
                case 0:
                    $row_num = mt_rand(1, $input_length) - 1;
                    $sub_value = mt_rand(-100, 100) * 0.01;
                    $closure = function ($input, $value) use ($row_num, $sub_value) {
                        if (is_null($input[$row_num])) {
                            return ['type' => 0, 'value' => $value];
                        }

                        // T += Xi * A
                        return ['type' => 0, 'value' => $value + $input[$row_num] * $sub_value];
                    };
                    $scenario[] = $closure;
                    unset($row_num, $sub_value);
                    break;
                case 1:
                    // Y = (Xi > A) ? B : f(T)
                    $row_num = mt_rand(1, $input_length) - 1;
                    $riffle = mt_rand(-10, 10);
                    $riffle_direction = (mt_rand(0, 1) == 0);
                    $output = mt_rand(1, count($output_dictionary)) - 1;
                    $closure = function ($input, $value) use ($row_num, $riffle, $riffle_direction, $output) {
                        if (is_null($input[$row_num])) {
                            return ['type' => 0, 'value' => $value];
                        }
                        if (($riffle_direction and ($input[$row_num] >= $riffle))
                            or (!$riffle_direction and ($input[$row_num] <= $riffle))
                        ) {
                            // Y = B
                            return ['type' => 1, 'value' => $output];
                        }

                        return ['type' => 0, 'value' => $value];
                    };
                    $scenario[] = $closure;
                    unset($row_num, $riffle, $riffle_direction, $output);
                    break;
                case 2:
                    // Y = (Y > A) ? B : f(T)
                    $riffle = mt_rand(-10, 10);
                    $riffle_direction = (mt_rand(0, 1) == 0);
                    $output = mt_rand(1, count($output_dictionary)) - 1;
                    $closure = function ($input, $value) use ($riffle, $riffle_direction, $output) {
                        if (($riffle_direction and ($value >= $riffle))
                            or (!$riffle_direction and ($value <= $riffle))
                        ) {
                            // Y = A
                            return ['type' => 1, 'value' => $output];
                        }

                        return ['type' => 0, 'value' => $value];
                    };
                    $scenario[] = $closure;
                    unset($row_num, $riffle, $riffle_direction, $output);
                    break;
                case 3:
                    // Just random: T += A
                    $sub_value = mt_rand(-100, 100) * 0.1;
                    $closure = function ($input, $value) use ($sub_value) {
                        return ['type' => 0, 'value' => $value + $sub_value];
                    };
                    $scenario[] = $closure;
                    unset($sub_value);
                    break;
                default:
                    // Add new leaf for tree
                    $closure = $this->generateBranch($rows, count($output_dictionary));
                    $scenario[] = $closure;
            }
        }
        unset($row_num, $closure, $sub_value);
        $closure = function ($input) use ($rows, $scenario, $output_dictionary) {
            $value = 1;
            foreach ($scenario as $scene) {
                /** @var \Closure $scene */
                $ret = $scene($input, $value);
                if ($ret['type'] == 1) {
                    return $output_dictionary[$ret['value']];
                } else {
                    $value = $ret['value'];
                }
            }

            $num = (int) floor($value * count($output_dictionary) / 30);

            return $output_dictionary[max(min($num, count($output_dictionary) - 1), 0)];
        };

        for ($i = 0; $i < 1000; $i++) {
            $input = [];
            $input_raw = [];

            for ($j = 0; $j < $input_length; $j++) {
                if (mt_rand(0, 20) == 0) {
                    $input_raw[] = null;
                    $input[] = null;
                } elseif ($rows[$j][0] == DecisionTree::CONTINUOUS) {
                    $value = mt_rand(0, 1000) / 1000;
                    $input_raw[] = $value;
                    $input[] = $value * ($rows[$j][2] - $rows[$j][1]) + $rows[$j][1];
                } else {
                    $value = mt_rand(1, count($rows[$j][1])) - 1;
                    $input_raw[] = $value;
                    $input[] = $rows[$j][1][$value];
                }
            }
            $value = $closure($input_raw);

            $data[] = array_merge($input, [$value]);
        }

        return [$data, $input_length, $output_dictionary];
    }

    /**
     * @param float   $dense
     * @param float[] $rand
     *
     * @return array
     * Generating some testing sparse data. Only 10% of input fields will be set in this test case.
     * This is a system where most of features is unknown i.e. recommendation system
     */
    protected function generateSingleSparseSampleForMultiple($dense = 0.1, $rand = [100, 1000])
    {
        // Generate values for output field: dic0, dic1, dic2,.. and then shuffle it
        $output_dictionary = range(0, mt_rand(3, 10));
        array_walk($output_dictionary, function (&$a) {
            $a = 'dic'.$a;
        });
        shuffle($output_dictionary);

        $input_length = mt_rand($rand[0], $rand[1]);
        $rows = [];
        $input_types = [];
        for ($i = 0; $i < $input_length; $i++) {
            if (mt_rand(0, 10) > 5) {
                // DecisionTree::CONTINUOUS
                if (mt_rand(0, 3) == 0) {
                    $min = 0;
                    $max = 1;
                } else {
                    $step = 1 / mt_rand(1, 10);
                    $min = $step * mt_rand(-10, 10);
                    $max = $min + $step * mt_rand(1, 10);
                }

                if (mt_rand(0, 2) == 0) {
                    /**
                     * Ti += f(Xi)
                     *
                     * Generate parabola
                     *
                     * @url https://www.wolframalpha.com/input/?i=x%5E2+%2B++x+%2B+1,+x+%3E%3D+0,+x+%3C%3D+1
                     * @url https://www.wolframalpha.com/input/?i=-47.1+%2A+x%5E2+%2B+55.9+%2A+x+%2B+8,+x+%3E%3D+0,+x+%3C%3D+1
                     * @url https://www.wolframalpha.com/input/?i=-231.65+%2A+x%5E2+%2B+453+%2A+x+%2B+24,+x+%3E%3D+0,+x+%3C%3D+1
                     * @url https://www.wolframalpha.com/input/?i=-27.7+%2A+x%5E2+%2B+42.7+%2A+x+%2B+11.9,+x+%3E%3D+0,+x+%3C%3D+1
                     */
                    $resolved_s = [];
                    foreach ($output_dictionary as $tmp) {
                        if (mt_rand(0, 10) == 0) {
                            $resolved_s[] = null;
                            continue;
                        } elseif (mt_rand(0, 5) == 0) {
                            $x = mt_rand(2, 998);
                            $x1 = mt_rand(0, $x - 1);
                            $system = [[0, mt_rand(1, 30)], [$x1 * 0.001, mt_rand(0, 10)], [$x * 0.001, 0]];
                        } else {
                            $x = mt_rand(2, 998);
                            $system = [[$x * 0.001, 0], [mt_rand($x + 1, 999) * 0.001, mt_rand(0, 10)], [1, mt_rand(1, 30)]];
                        }
                        $resolved = self::resolveQuadraticEquation($system);

                        $resolved_s[] = $resolved;
                    }
                    $feature_number = $i;
                    $closure = function ($input, &$output) use ($resolved_s, $feature_number) {
                        foreach ($resolved_s as $num => &$resolved) {
                            if (is_null($resolved)) {
                                continue;
                            }

                            $value = $input[$feature_number];
                            if (is_null($value)) {
                                continue;
                            }
                            $output[$num] += $resolved[0] * pow($value, 2) + $resolved[1] * $value + $resolved[2];
                        }
                    };
                    unset($system, $resolved, $resolved_s, $feature_number);
                } else {
                    // Generate random A.
                    // If A > B, then T0 += C else
                    //    If A > D, then T1 += E else
                    //       ...
                    $limits = [];
                    $count = mt_rand(1, 3);
                    $previous = mt_rand(1, 999) * 0.001;
                    for ($j = 0; $j < $count; $j++) {
                        if ($previous >= 1) {
                            break;
                        }
                        $limits[] = [$previous, mt_rand(0, count($output_dictionary) - 1), mt_rand(1, 10)];

                        $min = (int) floor($previous * 1000 + 1);
                        if ($min >= 1000) {
                            $limits[] = [$previous, mt_rand(0, count($output_dictionary) - 1), mt_rand(1, 10)];
                            break;
                        }
                        $previous = mt_rand($min, 999) * 0.001;
                        if ($previous >= 1) {
                            break;
                        }
                    }

                    $feature_number = $i;
                    $closure = function ($input, &$output) use ($limits, $feature_number) {
                        $value = $input[$feature_number];
                        if (is_null($value)) {
                            return;
                        }

                        foreach ($limits as &$limit) {
                            if ($value >= $limit[0]) {
                                $output[$limit[1]] += $limit[2];
                                break;
                            }
                        }
                    };
                    unset($count, $j, $limits, $feature_number);
                }
                $rows[] = [DecisionTree::CONTINUOUS, [$min, $max], $closure];
                $input_types[] = DecisionTree::CONTINUOUS;
            } elseif (mt_rand(0, 5) > 0) {
                // DecisionTree::NOMINAL
                // Generate random nominal field Xi
                // If Xi=A, then Y0 += B else
                //     If Xi=C, then Y1 += D else
                //         ...
                $dictionary_length = mt_rand(2, 7);
                $dictionary = range(1, $dictionary_length);
                if (mt_rand(0, 3) == 0) {
                    $step = mt_rand(1, 10);
                    array_walk($dictionary, function (&$a) use ($step) {
                        $a *= $step;
                    });
                } else {
                    array_walk($dictionary, function (&$a) {
                        $a = 'dic'.$a;
                    });
                }
                shuffle($dictionary);
                $modifiers = [];
                for ($j = 0; $j < $dictionary_length; $j++) {
                    $this_modifier = [];
                    foreach ($output_dictionary as $tmp) {
                        if (mt_rand(0, 3) == 0) {
                            $this_modifier[] = mt_rand(-50000, 50000) * 0.001;
                        } else {
                            $this_modifier[] = null;
                        }
                    }
                    $modifiers[] = $this_modifier;
                }
                $feature_number = $i;
                $closure = function ($input, &$output) use ($modifiers, $feature_number, $dictionary) {
                    if (is_null($input[$feature_number])) {
                        return;
                    }
                    $this_modifier = $modifiers[$input[$feature_number]];

                    foreach ($output as $num => &$value) {
                        if (!is_null($this_modifier[$num])) {
                            $value += $this_modifier[$num];
                        }
                    }
                };
                $rows[] = [DecisionTree::NOMINAL, [$dictionary], $closure];
                unset($modifiers, $dictionary, $dictionary_length, $feature_number, $tmp);
                $input_types[] = DecisionTree::NOMINAL;
            } else {
                // Random continuous field which does not provide any effect to output field
                if (mt_rand(0, 3) == 0) {
                    $min = 0;
                    $max = 1;
                } else {
                    $step = 1 / mt_rand(1, 10);
                    $min = $step * mt_rand(-10, 10);
                    $max = $min + $step * mt_rand(1, 10);
                }
                $rows[] = [null, [$min, $max]];
                $input_types[] = DecisionTree::CONTINUOUS;
            }
        }

        $data = [];
        for ($i = 0; $i < 10000; $i++) {
            $input = [];
            $input_raw = [];
            $output = [];
            foreach ($output_dictionary as $tmp) {
                $output[] = mt_rand(0, 2);
            }

            for ($j = 0; $j < $input_length; $j++) {
                if (mt_rand(0, 100) * 0.01 <= $dense) {
                    $input_raw[] = null;
                    $input[] = null;
                } elseif (is_null($rows[$j][0]) or ($rows[$j][0] == DecisionTree::CONTINUOUS)) {
                    $value = mt_rand(0, 10000) * 0.0001;
                    $input_raw[] = $value;
                    $input[] = $value * ($rows[$j][1][1] - $rows[$j][1][0]) + $rows[$j][1][0];
                } else {
                    $value = mt_rand(1, count($rows[$j][1][0])) - 1;
                    $input_raw[] = $value;
                    $input[] = $rows[$j][1][0][$value];
                }
            }
            foreach ($rows as $row) {
                if (is_null($row[0])) {
                    continue;
                }
                $closure = $row[2];
                $closure($input_raw, $output);
                if (max($output) >= 100) {
                    break;
                }
            }

            // Find the bigger Ti and return "i"
            arsort($output);
            $output_value = array_keys($output)[0];

            $data[] = array_merge($input, [$output_dictionary[$output_value]]);
        }

        return [$data, $input_length, $input_types];
    }

    protected static $_testMultipleSingleSample_data = [];

    public function dataMultipleSingleSample()
    {
        $data = [];
        for ($i = 0; ($i < 10000) and (count($data) < 0); $i++) {// @todo поправить
            $datum = $this->generateSingleSampleForMultiple();
            $column = array_count_values(array_column($datum[0], count($datum[0][0]) - 1));
            if (count($column) > 2) {
                $datum[] = null;
                $datum[] = 0.4;
                $datum[] = 0.9;
                $data[] = $datum;
            }
        }
        //
        $num = count($data);
        for ($i = 0; ($i < 10000) and (count($data) - $num < 0); $i++) {// @todo поправить
            $datum = $this->generateSingleSparseSampleForMultiple();
            $column = array_count_values(array_column($datum[0], count($datum[0][0]) - 1));
            if (count($column) > 2) {
                $datum[] = 0.3;
                $datum[] = 0.9;
                $data[] = $datum;
            }
        }
        //
        $num = count($data);
        for ($i = 0; ($i < 10000) and (count($data) - $num < 0); $i++) {// @todo поправить
            $datum = $this->generateSingleSparseSampleForMultiple(0.9, [3, 3]);
            $column = array_count_values(array_column($datum[0], count($datum[0][0]) - 1));
            if (count($column) > 2) {
                $datum[] = 0.25;
                $datum[] = 0.9;
                $data[] = $datum;
            }
        }
        //
        $num = count($data);
        for ($i = 0; count($data) - $num < 0; $i++) {// @todo поправить
            $datum = $this->dataTriangle();
            $datum[] = 0.9;
            $datum[] = 0.95;
            $data[] = $datum;
        }
        //
        for ($additional_circle_count = 0; $additional_circle_count < 5; $additional_circle_count++) {
            $num = count($data);
            for ($i = 0; ($i < 10000) and (count($data) - $num < 0); $i++) {// @todo поправить
                $datum = $this->dataCircle($additional_circle_count);
                if (is_null($datum)) {
                    continue;
                }
                $values = array_count_values(array_column($datum[0], 2));
                ksort($values);
                if (!isset($values[0], $values[1])) {
                    continue;
                }
                if (($values[1] < 0.05 * count($datum[0])) and ($values[1] < 100)) {
                    continue;
                }
                $datum[] = 0.9;
                $datum[] = 0.95;
                $data[] = $datum;
            }
        }
        //
        for ($dimension_number = 3; $dimension_number < 8; $dimension_number++) {
            $num = count($data);
            for ($i = 0; ($i < 10000) and (count($data) - $num < 5); $i++) {
                $datum = $this->dataNDimensionSpheres($dimension_number);
                if (is_null($datum)) {
                    continue;
                }
                $values = array_count_values(array_column($datum[0], $dimension_number));
                ksort($values);
                if (!isset($values[0], $values[1])) {
                    continue;
                }
                if (
                    (($values[1] < 0.05 * count($datum[0])) and ($values[1] < 100)) or
                    (($values[0] < 0.05 * count($datum[0])) and ($values[0] < 100))
                ) {
                    continue;
                }
                $datum[] = 0.8;
                $datum[] = 0.9;
                $data[] = $datum;
            }
        }

        // Put data to static property because it's faster then transfer array via params
        self::$_testMultipleSingleSample_data = [];
        foreach ($data as $num => &$datum) {
            self::$_testMultipleSingleSample_data[] = $datum[0];
            $datum[0] = $num;
        }

        return $data;
    }

    /**
     * Try to solve a system that is known to be solved
     *
     * @param integer   $data_int
     * @param integer   $input_length
     * @param integer[] $input_types Types for columns
     * @param double    $hard_limit  Minimal required success rate for a test
     * @param double    $soft_limit  Recommended success rate for a test
     *
     * @dataProvider dataMultipleSingleSample
     */
    public function testMultipleSingleSample($data_int, $input_length, $input_types = null,
                                             $hard_limit = 0.5, $soft_limit = 0.9)
    {
        $data = self::$_testMultipleSingleSample_data[$data_int];
        $input = [];
        $output = [];
        foreach ($data as &$datum) {
            $datum_input = [];
            $datum_output = null;
            foreach ($datum as $num => $value) {
                if ($num < $input_length) {
                    $datum_input[] = $value;
                } else {
                    $datum_output = $value;
                }
            }
            $input[] = $datum_input;
            $output[] = $datum_output;
        }

        $end_input = [];
        $end_output = [];
        $end_input_test = [];
        $end_output_test = [];
        $indexes = range(0, count($input) - 1);
        shuffle($indexes);
        foreach ($indexes as $i => $num) {
            if ($i < 0.9 * count($input)) {
                $end_input[] = $input[$num];
                $end_output[] = $output[$num];
            } else {
                $end_input_test[] = $input[$num];
                $end_output_test[] = $output[$num];
            }
        }
        unset($input, $output, $datum_input, $datum_output, $datum);

        $classifier = new DecisionTree();
        if (!is_null($input_types)) {
            $classifier->setInstanceColumnTypes($input_types);
        }
        $classifier->train($end_input, $end_output);
        if (!file_exists(__DIR__.'/../../php-ml-tests/')) {
            mkdir(__DIR__.'/../../php-ml-tests/');
        }
        file_put_contents(__DIR__.'/../../php-ml-tests/tree-'.microtime(true).'.html', $classifier->getHtml(), LOCK_EX);

        $predicted = $classifier->predict($end_input_test);
        $success = 0;
        foreach ($end_output_test as $i => $num) {
            $success += ($num === $predicted[$i]) ? 1 : 0;
        }

        $this->assertGreaterThanOrEqual(floor(count($end_output_test) * $hard_limit), $success);
        if (floor(count($end_output_test) * $soft_limit) > $success) {
            $this->markTestIncomplete((floor($success * 1000 / count($end_output_test)) / 10).'% success rate');
        }
    }

    /**
     * @param double[][] $system
     *
     * @return double[]
     *
     * Resolve quadratic equation using matrix
     * @url https://en.wikipedia.org/wiki/Quadratic_equation
     */
    protected static function resolveQuadraticEquation($system)
    {
        $matrix = [
            [$system[0][0], pow($system[0][0], 2), 1, $system[0][1]],
            [$system[1][0], pow($system[1][0], 2), 1, $system[1][1]],
            [$system[2][0], pow($system[2][0], 2), 1, $system[2][1]],

            [$system[1][0] - $system[0][0], pow($system[1][0], 2) - pow($system[0][0], 2), 0,
             $system[1][1] - $system[0][1]],
            [$system[2][0] - $system[0][0], pow($system[2][0], 2) - pow($system[0][0], 2), 0,
             $system[2][1] - $system[0][1]],
        ];
        $matrix[] = [1, $matrix[3][1] / $matrix[3][0], 0, $matrix[3][3] / $matrix[3][0]];// 5
        $matrix[] = [1, $matrix[4][1] / $matrix[4][0], 0, $matrix[4][3] / $matrix[4][0]];// 6

        $matrix[] = [0, $matrix[6][1] - $matrix[5][1], 0, $matrix[6][3] - $matrix[5][3]];// 7
        $matrix[] = [0, 1, 0, $matrix[7][3] / $matrix[7][1]];// 8

        $matrix[] = [1, 0, 0, $matrix[5][3] - $matrix[5][1] * $matrix[8][3]];// 9

        $matrix[] = [$matrix[0][0] - $matrix[0][0] * $matrix[9][0],
                     $matrix[0][1] - $matrix[0][0] * $matrix[9][1],
                     $matrix[0][2] - $matrix[0][0] * $matrix[9][2],
                     $matrix[0][3] - $matrix[0][0] * $matrix[9][3],];// 10
        $matrix[] = [$matrix[10][0] - $matrix[10][1] * $matrix[8][0],
                     $matrix[10][1] - $matrix[10][1] * $matrix[8][1],
                     $matrix[10][2] - $matrix[10][1] * $matrix[8][2],
                     $matrix[10][3] - $matrix[10][1] * $matrix[8][3],];// 11

        return [$matrix[9][3], $matrix[8][3], $matrix[10][3]];
    }

    /**
     * Generate a right triangle on 2D plan and populate points on the plan.
     * So Decision Tree have to find out if a point is in the triangle or not
     *
     * @return array
     */
    public function dataTriangle()
    {
        $angle = mt_rand(1, 890) * 0.1;
        $bottom_cat_length = mt_rand(50, 150) * 0.01;

        $x_offset = mt_rand(-1500, 500) * 0.001;
        $y_offset = mt_rand(-1500, 500) * 0.001;
        $closure = function ($x, $y) use ($angle, $bottom_cat_length, $x_offset, $y_offset) {
            if (($x < $x_offset) or ($x > $bottom_cat_length + $x_offset)) {
                return false;
            }
            $y_max = $bottom_cat_length / tan(deg2rad($angle)) + $y_offset;
            if (($y < $y_offset) or ($y > $y_max + $y_offset)) {
                return false;
            }

            $y_max_this_x = ($x - $x_offset) / tan(deg2rad($angle));

            return $y - $y_offset <= $y_max_this_x;
        };

        $data = [];
        for ($i = 0; $i < 10000; $i++) {
            $x = mt_rand(-2000, 2000) * 0.001;
            $y = mt_rand(-2000, 2000) * 0.001;

            $data[] = [$x, $y, $closure($x, $y) ? 1 : 0];
        }

        return [$data, 2, [DecisionTree::CONTINUOUS, DecisionTree::CONTINUOUS]];
    }

    public function dataCircle($additional_circle_count)
    {
        // Generate values for output field: dic0, dic1, dic2,.. and then shuffle it
        $output_dictionary = range(0, mt_rand(3, 10));
        array_walk($output_dictionary, function (&$a) {
            $a = 'dic'.$a;
        });
        shuffle($output_dictionary);

        /**
         * @var float[][] $circles
         */
        $circles = [
            [mt_rand(200, 800) * 0.01, mt_rand(200, 800) * 0.01, mt_rand(10, 2000) * 0.001],
        ];
        $iteration_cycle_count = 0;

        // Second circle
        do {
            if ($iteration_cycle_count++ > 10000) {
                return null;
            }
            $x = mt_rand(200, 800) * 0.01;
            $y = mt_rand(200, 800) * 0.01;
            $r = mt_rand(10, 1500) * 0.001;

            $r_between = sqrt(pow($x - $circles[0][0], 2) + pow($y - $circles[0][1], 2));
            if ($r_between <= $circles[0][2]) {
                // Second point is within first circle
                continue;
            }
            $r_between2 = -$r_between + $r + $circles[0][2];
        } while (($r_between2 < 0.05 * $r_between) or ($r_between2 > 0.2 * $r_between));
        $circles[] = [$x, $y, $r];

        // Third circle
        do {
            if ($iteration_cycle_count++ > 10000) {
                return null;
            }
            $x = mt_rand((int) (1000 * ($circles[1][0] - $circles[1][2])),
                    (int) (1000 * ($circles[1][0] + $circles[1][2]))) * 0.001;
            $y = mt_rand((int) (1000 * ($circles[1][1] - $circles[1][2])),
                    (int) (1000 * ($circles[1][1] + $circles[1][2]))) * 0.001;
            $r = mt_rand(100, 9000) * 0.0001;

            $r_between = sqrt(pow($x - $circles[1][0], 2) + pow($y - $circles[1][1], 2));
        } while (($r_between > $circles[1][2]) or ($r > $circles[1][2]) or ($r + $r_between < $circles[1][2]));
        $circles[] = [$x, $y, $r];

        for ($i = 0; $i < $additional_circle_count; $i++) {
            $circles[] = [mt_rand(100, 900) * 0.01, mt_rand(100, 900) * 0.01, mt_rand(10, 1000) * 0.001];
        }

        $closure = function ($x, $y) use ($circles) {
            foreach ($circles as &$circle) {
                $r_between = sqrt(pow($x - $circle[0], 2) + pow($y - $circle[1], 2));
                if ($r_between <= $circle[2]) {
                    return true;
                }
            }

            return false;
        };

        $data = [];
        for ($i = 0; $i < 20000; $i++) {
            $x = mt_rand(-10000, 20000) * 0.001;
            $y = mt_rand(-10000, 20000) * 0.001;

            $data[] = [$x, $y, $closure($x, $y) ? 1 : 0];
        }

        return [$data, 2, [DecisionTree::CONTINUOUS, DecisionTree::CONTINUOUS]];
    }

    /**
     * Populate N-dimension spheres
     *
     * @param $dimension_number
     * @return array
     */
    public function dataNDimensionSpheres($dimension_number) {
        // Generate values for output field: dic0, dic1, dic2,.. and then shuffle it
        $output_dictionary = range(0, mt_rand(3, 10));
        array_walk($output_dictionary, function (&$a) {
            $a = 'dic'.$a;
        });
        shuffle($output_dictionary);

        //
        $spheres = [];
        $sphere_count = mt_rand(2, 10);
        for ($i = 0; $i < $sphere_count; $i++) {
            $sphere = [mt_rand(10, 10000) * 0.001];
            for ($j = 0; $j < $dimension_number; $j++) {
                $sphere[] = mt_rand(-10000, 10000) * 0.001;
            }
            $spheres[] = $sphere;
        }

        $closure = function ($input) use (&$spheres) {
            $dimension_number = count($input);
            $dimension_number_r = 1 / $dimension_number;
            foreach ($spheres as &$sphere) {
                $r = 0;
                foreach ($input as $num => $value) {
                    $r += pow(pow($sphere[$num + 1] - $value, 2), $dimension_number_r);
                }
                if ($r <= $sphere[0]) {
                    return true;
                }
            }

            return false;
        };

        $data = [];
        for ($i = 0; $i < 20000; $i++) {
            $input = [];
            for ($j = 0; $j < $dimension_number; $j++) {
                $input[] = mt_rand(-10000, 20000) * 0.001;
            }

            $data[] = array_merge($input, [$closure($input) ? 1 : 0]);
        }

        return [$data, $dimension_number, array_fill(0, $dimension_number, DecisionTree::CONTINUOUS)];
    }

    public function testNyan() {
        // @todo Удали меня
        return;
        $x = mt_rand(2, 998);
        $system = [[$x * 0.001, 0], [mt_rand($x + 1, 999) * 0.001, mt_rand(0, 10)], [1, mt_rand(1, 30)]];
        $resolved = self::resolveQuadraticEquation($system);
        print_r($resolved);
    }
}
