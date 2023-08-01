<?php

namespace App\Services;

class RecommendService
{
    const MAX_ITERS = 50;
    const EPSILON = 1e-3;
    const BREAK_EPSILON = 1e-3;
    const THRESHOLD_TO_ADVOID_DIVIDE_BY_ZERO = 1e-5;
    const VERY_SMALL_DISTANCE = 1e-5;

    function get_label_to_center_position($label)
    {
        if (!is_string($label)) {
            $label = strval(round($label)); // avoid float
        }

        $label_position = array(
            '0' => 0, // not co-author -> at column with index 0
            '1' => 1,  // co-author
        );

        return intval($label_position[$label]);
    }

    function get_center_position_to_label($position)
    {
        if (!is_string($position)) {
            $position = strval(round($position)); // avoid float
        }

        $position_label = array(
            '0' => 0, // not co-author -> at column with index 0
            '1' => 1,  // co-author
        );

        return $position_label[$position];
    }

    function init_matrix_m($X, $Y, $K, $M = 2, $M_prime = 10)
    {
        /*
        init matrix for m = M_matrix[i] with M and M'
        M_matrix = array of coefficients
            case 1: m = M  , unsupervised
            case 2: m = M' , supervised
            case 3: m = M  , supervised (j != k -> m = M)
        */
        $m_matrix = array_fill(0, count($X), array_fill(0, $K, $M));
        for ($i = 0; $i < count($X); $i++) {
            $y_i = $Y[$i];
            if (!is_nan($y_i)) {
                $m_matrix[$i][$this->get_label_to_center_position($y_i)] = $M_prime;
            }
        }
        return $m_matrix;
    }

    function init_centroids($X, $Y, $K)
    {
        return $this->init_centroids_based_on_labels($X, $Y, $K);
    }

    function init_centroids_based_on_labels($X, $Y, $K)
    {
        // Labeled points = "name of label" : [index of the datapoint that belongs to the label]
        $labeled_points = array();
        $D = count($X[0]);
        $centroids = array();

        // 1. get supervised datapoints -> distribute them into a dictionary (key = label)
        for ($i = 0; $i < count($Y); $i++) {
            $k = $Y[$i];
            if (!is_nan($k)) {
                if (array_key_exists($k, $labeled_points)) {
                    $labeled_points[$k] = array_merge($labeled_points[$k], [$X[$i]]);
                } else {
                    $labeled_points[$k] = [$X[$i]];
                }
            }
        }

        // 2. get centroid, which is the midpoint of same labeled datapoints
        echo 'Init centroids for clusters with supervised datapoints: ' . PHP_EOL;
        $labeled_centroids_num = 0;
        foreach ($labeled_points as $k => $points) {
            $centroid = array();
            $n = count($points);
            for ($d = 0; $d < $D; $d++) {
                $sum = 0;
                foreach ($points as $point) {
                    $sum += $point[$d];
                }
                $centroid[] = $sum / $n;
            }
            $centroids[$this->get_label_to_center_position($k)] = $centroid;
            $labeled_centroids_num++;
        }
        echo '  Init ' . $labeled_centroids_num . ' centroids' . PHP_EOL;
        echo '  Finished!' . PHP_EOL;

        // 3. if there are clusters that still do not have a centroid
        //    - get the farthest datapoint from the other centroids
        //    - and make it the centroid for the cluster
        echo 'Init centroids for clusters without supervised datapoints' . PHP_EOL;
        $num_of_new_centroid = $K - $labeled_centroids_num;
        echo '  Found ' . $num_of_new_centroid . ' centroid(s) need to be init' . PHP_EOL;
        if ($num_of_new_centroid > 0) {
            for ($k = $labeled_centroids_num; $k < $K; $k++) {
                // get the datapoint x that has the biggest min distance to all centroids (x - c[i])
                $distance = array();
                foreach ($X as $x) {
                    // get the min distance from x to each c[i]
                    $d = array();
                    for ($i = 0; $i < count($centroids); $i++) {
                        $d[] = sqrt(array_sum(array_map(function ($a, $b) {
                            return pow($a - $b, 2);
                        }, $x, $centroids[$i])));
                    }
                    $distance[] = min($d);
                }
                $centroids[$k] = $X[array_search(max($distance), $distance)];
            }
        }
        echo '  Finished!' . PHP_EOL;

        // echo PHP_EOL . 'Centroids:';
        // print_r($centroids);

        return $centroids;
    }

    function calculate_centroids($X, $U, $K, $D, $M_matrix) {
        $C = array_fill(0, $K, array_fill(0, $D, 0));

        // for each centroid C_j
        for ($j = 0; $j < $K; $j++) {
            $m_ik_column = array_column($M_matrix, $j);
            $u_ik_column = array_column($U, $j);
            $sum_jk = array_sum(array_map(function ($u, $m) {
                return pow($u, $m);
            }, $u_ik_column, $m_ik_column));

            // for each dimension of C_j
            for ($x = 0; $x < $D; $x++) {
                $denom = $sum_jk;
                $numerator = array_sum(array_map(function ($u, $m, $x) {
                    return pow($u, $m) * $x;
                }, $u_ik_column, $m_ik_column, array_column($X, $x)));
                $C[$j][$x] = $numerator / $denom;
            }
        }

        return $C;
    }

    function update_unsupervised_weight($X_i, $C, $K, $M) {
        /*
        input:
            - $X_i: 1 row of data (1 datapoint), which is unsupervised
            - $C: the clusters
            - $M
        process:
            - cal the $u_ij
        output:
            - $U_i (the row of $u_ij for the input datapoint)
        */
        $U_i = array_fill(0, $K, 0);
        $D_i = array_fill(0, $K, 0);

        for ($j = 0; $j < $K; $j++) {
            $D_ij = sqrt(array_sum(array_map(function ($x, $c) {
                return pow($x - $c, 2);
            }, $X_i, $C[$j])));
            $D_i[$j] = $D_ij;
            if ($D_i[$j] <= self::THRESHOLD_TO_ADVOID_DIVIDE_BY_ZERO) {
                $D_i[$j] = self::VERY_SMALL_DISTANCE;
            }
        }

        for ($k = 0; $k < $K; $k++) {
            $d = $D_i[$k];
            $_sum = 0;
            for ($j = 0; $j < $K; $j++) {
                $_sum += pow($d / $D_i[$j], 2 / ($M - 1));
            }
            $U_i[$k] = pow($_sum, -1);
        }

        return $U_i;
    }

    function calculate_u_right_side($m, $d_ij) {
        /*
        calcualate the right side of the expression in Step 2.1 and Step 2.2 of function below
        */
        return pow(1 / ($m * pow($d_ij, 2)), (1 / ($m - 1)));
    }

    function update_supervised_weight($X_i, $y_i, $C, $K, $M, $M_prime) {
        /*
        input:
            - $X_i: 1 row of data (1 datapoint), which is supervised
            - $y_i: the label for the datapoint
            - $C: the clusters
            - $M, $M_prime
        process:
            - cal the u -> 2 cases:
                + $u_ij (X_i with C_j, j != the label)
                + $u_ik (X_i with C_k, k == the label)
        output:
            - $U_i (the row of $u_ij for the input datapoint)
        */
        $U_i = array_fill(0, $K, 0);
        $D_i = array_fill(0, $K, 0);

        // 1. calculate $d_ij
        for ($j = 0; $j < $K; $j++) {
            $D_i[$j] = sqrt(array_sum(array_map(function ($x, $c) {
                return pow($x - $c, 2);
            }, $X_i, $C[$j])));
            if ($D_i[$j] <= self::THRESHOLD_TO_ADVOID_DIVIDE_BY_ZERO) {
                $D_i[$j] = self::VERY_SMALL_DISTANCE;
            }
        }

        $d_min = min($D_i);
        $d_i = array_map(function ($d) use ($d_min) {
            return $d / $d_min;
        }, $D_i);

        // 2. calculate $u_ij
        $k = (int)$y_i;  // $k = the label
        $sum_ij = 0;    // $sum_ij = the sum of $u_ij (j != k)

        // 2.1. calculate $u_ij with j != k
        for ($j = 0; $j < $K; $j++) {
            if ($j == $k) {
                continue;
            }
            $u_ij = $this->calculate_u_right_side($M, $d_i[$j]);
            $U_i[$j] = $u_ij;
            $sum_ij += $u_ij;
        }

        // 2.2. calculate $u_ik
        // 2.2.1. calculate the right side, and the exponential at the left side denom
        $right_side = $this->calculate_u_right_side($M_prime, $d_i[$k]);
        $denom_exponential = ($M_prime - $M) / ($M_prime - 1);

        // 2.2.2. calculate $u_ik
        $u_ik = 0;
        while (true) {
            $left_side = $u_ik / pow(($u_ik + $sum_ij), $denom_exponential);
            if (abs($right_side - $left_side) < self::EPSILON) {
                break;
            }
            $u_ik += self::EPSILON;
        }
        $U_i[$k] = $u_ik;

        // 2.2.3. normalize $u_ij
        $sum_ij += $u_ik;
        for ($j = 0; $j < $K; $j++) {
            $U_i[$j] = $U_i[$j] / $sum_ij;
        }

        return $U_i;
    }

    public function array_2d_any($array, $condition)
    {
        foreach ($array as $row) {
            if (array_search(true, array_map($condition, $row)) !== false) {
                return true;
            }
        }
        return false;
    }

    function sSMC_FCM($data, $Y, $K, $M = 2, $M_prime = 15) {
        // 0. get data & dimension
        $X = $data;
        $D = count($X[0]);
        $N = count($data);

        // 2. Initialize
        // 2.1. initialize U matrix and M matrix
        echo "Init U matrix and M matrix\n";
        $U = array_fill(0, $N, array_fill(0, $K, 0));
        $M_matrix = $this->init_matrix_m($X, $Y, $K, $M, $M_prime);
        echo "  Done!\n";

        // 2.2. initialize centroids
        echo "\nInit centroids\n";
        $C = $this->init_centroids($X, $Y, $K);
        echo "  Done!\n";
        echo "  Centroids:\n";
        print_r($C);

        // 3. Looping & Calculate weights
        echo "\nStart classify datapoints:\n";
        for ($z = 0; $z < self::MAX_ITERS; $z++) {
            echo "loop: " . $z . "\n";

            // 3.1. Calculate weight (update U_ij)
            $U_new = array_fill(0, $N, array_fill(0, $K, 0));
            for ($i = 0; $i < count($X); $i++) {
                $X_i = $X[$i];
                if (is_nan($Y[$i])) {
                    $U_new[$i] = $this->update_unsupervised_weight($X_i, $C, $K, $M);
                } else {
                    $U_new[$i] = $this->update_supervised_weight($X_i, $Y[$i], $C, $K, $M, $M_prime);
                }
            }

            // 3.2. Calculate centroids (update centroids)
            $C = $this->calculate_centroids($X, $U_new, $K, $D, $M_matrix);

            // 3.3. Check the convergence of matrix U with EPSILON -> repeat 3.1 and 3.2 if diff > EPSILON
            $diff = array_map(function ($u, $u_new) {
                return abs($u - $u_new);
            }, $U, $U_new);
            if (!$this->array_2d_any($diff, function ($val) {
                return $val > self::BREAK_EPSILON;
            })) {
                break;
            } else {
                $U = $U_new;
            }
        }

        return [$U, $C];
    }

    public function get_result_df($id, $final_weights, $data, $columns)
    {
        $result = [];
        foreach ($final_weights as $w) {
            $result[] = array_search(max($w), $w);
        }

        $result_df = array_column($id, null, 'id');
        $result_df = array_merge($result_df, $data);
        $result_df = array_merge($result_df, array_column([$result], 'status', 'id'));

        $result_df = array_filter($result_df, function ($key) {
            return $key >= 0;
        }, ARRAY_FILTER_USE_KEY);

        $result_df = array_map(function ($value) {
            return intval($value);
        }, $result_df);

        $result_df = array_map(function ($value) {
            return $this->get_center_position_to_label($value);
        }, $result_df);

        return $result_df;
    }

    public function run_algo($data, $status, $num_clusters, $columns)
    {
        [$final_weights, $centers] = $this->sSMC_FCM($data, $status, $num_clusters);

        $result_df = $this->get_result_df($data['id'], $final_weights, $data, $columns);

        return $result_df;
    }


}
