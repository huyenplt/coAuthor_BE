<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Facades\DB;
use App\Models\PaperAuthor;
use App\Models\CoAuthor;
use App\Models\CoAuthor1;
use App\Models\CoAuthor2;

class CoAuthorService
{
    public function getCoAuthorsFromPaperAuthors()
    {
        $coAuthors = PaperAuthor::select(
            'pa1.author_id as author_id1',
            'pa2.author_id as author_id2',
            'pa1.paper_id',
            'p.year as paper_year'
        )
            ->from('paper_authors as pa1')
            ->join('paper_authors as pa2', function ($join) {
                $join->on('pa1.paper_id', '=', 'pa2.paper_id')
                    ->where('pa1.author_id', '<', 'pa2.author_id');
            })
            ->join('papers as p', 'p.id', '=', 'pa1.paper_id')
            ->whereRaw('pa1.author_id <> pa2.author_id') // Kiểm tra author_id1 khác author_id2
            ->whereRaw('pa1.author_id < pa2.author_id') // Sắp xếp các tác giả theo thứ tự tăng dần
            ->distinct()
            ->get();

        return $coAuthors;
    }

    public function importCoAuthors()
    {
        $coAuthors = $this->getCoAuthorsFromPaperAuthors();
        foreach ($coAuthors as $coAuthor) {
            $authorId1 = $coAuthor->author_id1;
            $authorId2 = $coAuthor->author_id2;
            $paperId = $coAuthor->paper_id;
            $paperYear = $coAuthor->paper_year;

            // Tạo một bản ghi mới trong bảng co_authors
            CoAuthor::create([
                'author_id1' => $authorId1,
                'author_id2' => $authorId2,
                'paper_id' => $paperId,
                'paper_year' => $paperYear
            ]);
        }

        return $coAuthors;
    }

    public function getCandidates($split_year)
    {
        $candidateAll = CoAuthor::select(
            'author_id1',
            'author_id2',
            'paper_year'
        )->distinct()->get();

        $size_20_percent = round(count($candidateAll) * 0.2);

        // Chọn ngẫu nhiên các chỉ số của phần tử để tạo mảng con 20%
        $random_keys = array_rand($candidateAll->toArray(), $size_20_percent);
        $array_20_percent = array_intersect_key($candidateAll->toArray(), array_flip($random_keys));

        // Mảng con 80% là phần còn lại của mảng gốc
        $array_80_percent = array_diff_key($candidateAll->toArray(), $array_20_percent);

        // Import dữ liệu từ $array_20_percent
        foreach ($array_20_percent as $data) {
            DB::table('coauthor1')->insert([
                'author_id1' => $data['author_id1'],
                'author_id2' => $data['author_id2'],
                'paper_year' => $data['paper_year']
            ]);
        }

        // Import dữ liệu từ $array_80_percent
        foreach ($array_80_percent as $data) {
            DB::table('coauthor2')->insert([
                'author_id1' => $data['author_id1'],
                'author_id2' => $data['author_id2'],
                'paper_year' => $data['paper_year']
            ]);
        }

        $candidates = CoAuthor2::select(
            'author_id1',
            'author_id2',
        )
            ->where('paper_year', '<', $split_year)
            ->distinct()
            ->get();

        $repeatedAuthor1 = CoAuthor2::select('author_id1')
            ->where('paper_year', '<', $split_year)
            ->groupBy('author_id1')
            ->havingRaw('COUNT(author_id1) > 1')
            ->get();

        foreach ($repeatedAuthor1 as $item) {
            $author1ids = CoAuthor2::select('author_id2')
                ->where('paper_year', '<', $split_year)
                ->where('author_id1', $item->author_id1)
                ->orderBy('author_id2', 'asc')
                ->distinct()
                ->get();

            // Duyệt qua từng phần tử trong dãy
            for ($i = 0; $i < count($author1ids); $i++) {
                // Duyệt qua các phần tử còn lại trong dãy
                for ($j = $i + 1; $j < count($author1ids); $j++) {
                    // Tạo cặp số và thêm vào mảng pairs
                    $pair = [
                        'author_id1' => $author1ids[$i]->author_id2,
                        'author_id2' => $author1ids[$j]->author_id2,
                    ];
                    $candidates[] = $pair;
                }
            }
        }

        // Loại bỏ các giá trị bị lặp
        $uniqueCandidates = array_values(array_unique($candidates->toArray(), SORT_REGULAR));

        return $uniqueCandidates;
        // return count($uniqueCandidates);

        // return $candidates->count();
    }

    public function importCandidates($split_year)
    {
        $split_year = 1995;
        set_time_limit(0);
        $candidates = $this->getCandidates($split_year);
        $data = [];

        foreach ($candidates as $candidate) {
            $data[] = [
                'author_id1' => $candidate['author_id1'],
                'author_id2' => $candidate['author_id2'],
                'label' => 0,
            ];
        }

        Candidate::insert($data);

        DB::table('candidates2')
            ->join('co_author', function ($join) {
                $join->on('candidates2.author_id1', '=', 'co_author.author_id1')
                    ->on('candidates2.author_id2', '=', 'co_author.author_id2');
            })
            ->where('co_author.paper_year', '>=', $split_year)
            ->update(['candidates2.label' => 1]);

        $candidatesTest = CoAuthor1::select(
            'author_id1',
            'author_id2',
        )
            ->distinct()
            ->get();

        $repeatedAuthor1 = CoAuthor1::select('author_id1')
            ->groupBy('author_id1')
            ->havingRaw('COUNT(author_id1) > 1')
            ->get();

        foreach ($repeatedAuthor1 as $item) {
            $author1ids = CoAuthor1::select('author_id2')
                ->where('author_id1', $item->author_id1)
                ->orderBy('author_id2', 'asc')
                ->distinct()
                ->get();

            // Duyệt qua từng phần tử trong dãy
            for ($i = 0; $i < count($author1ids); $i++) {
                // Duyệt qua các phần tử còn lại trong dãy
                for ($j = $i + 1; $j < count($author1ids); $j++) {
                    // Tạo cặp số và thêm vào mảng pairs
                    $pair = [
                        'author_id1' => $author1ids[$i]->author_id2,
                        'author_id2' => $author1ids[$j]->author_id2,
                    ];
                    $candidatesTest[] = $pair;
                }
            }
        }

        // Loại bỏ các giá trị bị lặp
        $uniqueCandidates = array_values(array_unique($candidatesTest->toArray(), SORT_REGULAR));

        $dataTest = [];

        foreach ($uniqueCandidates as $item) {
            $dataTest[] = [
                'author_id1' => $item['author_id1'],
                'author_id2' => $item['author_id2']
            ];
        }

        Candidate::insert($dataTest);

        return $candidates;
    }

    public function labeledCandidates($split_year)
    {
        DB::table('candidates2')
            ->join('co_author', function ($join) {
                $join->on('candidates2.author_id1', '=', 'co_author.author_id1')
                    ->on('candidates2.author_id2', '=', 'co_author.author_id2');
            })
            ->where('co_author.paper_year', '>=', $split_year)
            ->update(['candidates2.label' => 1]);
    }

    public function calculateMeasures($measures)
    {
        $this->calculateRA();
        // return $this->calculateAA();
        // foreach ($measures as $measure) {
        //     if (in_array("Common Neighbor (CN)", $measure)) {
        //         return $this->calculateCN();
        //     }
        //     // if (in_array("Adamic-Adar (AA)", $measure)) {
        //     //     $this->calculateAA();
        //     // }
        //     // if (in_array("Jaccard Coefficient (JC)", $measure)) {
        //     //     $this->calculateJC();
        //     // }
        //     // if (in_array("Resource Allocation (RA)", $measure)) {
        //     //     $this->calculateRA();
        //     // }
        // }
    }

    public function calculateCN()
    {
        $data = $this->getCommonNeighbor();
        // return $data;
        $candidates = Candidate::all();
        foreach ($candidates as $index => $candidate) {
            $candidate->measure1 = count($data[$index]);
            // $candidate->common_neighbor = $data[$index];
            $candidate->save();
        }
    }

    public function calculateAA()
    {
        $data = $this->getCommonNeighbor();
        $candidates = Candidate::all();

        foreach ($candidates as $index => $candidate) {
            if (count($data[$index]) > 0) {
                $total = 0;
                foreach ($data[$index] as $neighbor) {
                    $count = Candidate::where('author_id1', $neighbor)->count();
                    if ($count == 1) $total += 100000;
                    else $total += 1 / log($count);
                }
                $candidate->measure2 = $total;
            } else $candidate->measure2 = 0;
            $candidate->save();
        }
    }

    public function calculateJC()
    {
        $candidates = Candidate::all();

        set_time_limit(0);
        foreach ($candidates as $candidate) {
            $neighbor1 = Candidate::where('author_id1', $candidate->author_id1)->count();
            $neighbor2 = Candidate::where('author_id1', $candidate->author_id2)->count();

            $result = $candidate->measure1 / ($neighbor1 + $neighbor2);

            $candidate->measure3 = $result;
            $candidate->save();
        }
    }

    public function calculateRA()
    {
        $data = $this->getCommonNeighbor();
        $candidates = Candidate::all();

        foreach ($candidates as $index => $candidate) {
            if (count($data[$index]) > 0) {
                $total = 0;
                foreach ($data[$index] as $neighbor) {
                    $count = Candidate::where('author_id1', $neighbor)->count();
                    if ($count == 0) $total += 1;
                    else $total += 1 / $count;
                }
                $candidate->measure4 = $total;
            } else $candidate->measure4 = 0;
            $candidate->save();
        }
    }

    public function getCommonNeighbor()
    {
        $perPage = 100; // Số lượng bản ghi trên mỗi trang
        $totalCandidates = Candidate::count(); // Tổng số lượng candidates

        $test = [];
        $totalPages = ceil($totalCandidates / $perPage); // Tính tổng số trang

        for ($page = 1; $page <= $totalPages; $page++) {
            $offset = ($page - 1) * $perPage;

            $candidates = Candidate::select('author_id1', 'author_id2')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            foreach ($candidates as $candidate) {
                $neighbor1 = Candidate::select('author_id2')
                    ->where('author_id1', $candidate->author_id1)
                    ->pluck('author_id2')
                    ->toArray();

                $neighbor2 = Candidate::select('author_id2')
                    ->where('author_id1', $candidate->author_id2)
                    ->pluck('author_id2')
                    ->toArray();
                set_time_limit(0);
                $commonElements = array_intersect($neighbor1, $neighbor2);

                // $neighborMeasure = count($commonElements);
                array_push($test, $commonElements);
            }
        }

        return $test;
    }

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
            if ($y_i != null) {
                $m_matrix[$i][$y_i] = $M_prime;
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
            if ($k != null) {
                if (array_key_exists($k, $labeled_points)) {
                    $labeled_points[$k] = array_merge($labeled_points[$k], [$X[$i]]);
                } else {
                    $labeled_points[$k] = [$X[$i]];
                }
            }
        }

        // 2. get centroid, which is the midpoint of same labeled datapoints
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
            $centroids[$k] = $centroid;
            $labeled_centroids_num++;
        }

        // 3. if there are clusters that still do not have a centroid
        //    - get the farthest datapoint from the other centroids
        //    - and make it the centroid for the cluster
        $num_of_new_centroid = $K - $labeled_centroids_num;
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

        return $centroids;
    }

    function calculate_centroids($X, $U, $K, $D, $M_matrix)
    {
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

    function update_unsupervised_weight($X_i, $C, $K, $M)
    {
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

    function calculate_u_right_side($m, $d_ij)
    {
        /*
        calcualate the right side of the expression in Step 2.1 and Step 2.2 of function below
        */
        return pow(1 / ($m * pow($d_ij, 2)), (1 / ($m - 1)));
    }

    function update_supervised_weight($X_i, $y_i, $C, $K, $M, $M_prime)
    {
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

    function sSMC_FCM($ids, $data, $Y, $K, $M = 2, $M_prime = 10)
    {
        // 0. get data & dimension
        $X = $data;
        $D = count($X[0]);
        $N = count($data);

        // 2. Initialize
        // 2.1. initialize U matrix and M matrix
        $U = array_fill(0, $N, array_fill(0, $K, 0));
        $M_matrix = $this->init_matrix_m($X, $Y, $K, $M, $M_prime);

        // 2.2. initialize centroids
        $C = $this->init_centroids($X, $Y, $K);

        // 3. Looping & Calculate weights
        for ($z = 0; $z < self::MAX_ITERS; $z++) {
            // 3.1. Calculate weight (update U_ij)
            $U_new = array_fill(0, $N, array_fill(0, $K, 0));
            for ($i = 0; $i < count($X); $i++) {
                $X_i = $X[$i];
                if ($Y[$i] == null) {
                    $U_new[$i] = $this->update_unsupervised_weight($X_i, $C, $K, $M);
                } else {
                    $U_new[$i] = $this->update_supervised_weight($X_i, $Y[$i], $C, $K, $M, $M_prime);
                }
            }

            // 3.2. Calculate centroids (update centroids)
            $C = $this->calculate_centroids($X, $U_new, $K, $D, $M_matrix);

            // return [$U, $U_new];
            // 3.3. Check the convergence of matrix U with EPSILON -> repeat 3.1 and 3.2 if diff > EPSILON
            // $diff = array_map(function ($u, $u_new) {
            //     return abs($u - $u_new);
            // }, $U, $U_new);
            // if (!$this->array_2d_any($diff, function ($val) {
            //     return $val > self::BREAK_EPSILON;
            // })) {
            //     break;
            // } else {
            //     $U = $U_new;
            // }

            $diff = array();
            for ($i = 0; $i < count($U); $i++) {
                for ($j = 0; $j < count($U[$i]); $j++) {
                    $diff[$i][$j] = abs($U[$i][$j] - $U_new[$i][$j]);
                }
            }

            $hasSignificantDifference = false;
            for ($i = 0; $i < count($diff); $i++) {
                for ($j = 0; $j < count($diff[$i]); $j++) {
                    if ($diff[$i][$j] > self::BREAK_EPSILON) {
                        $hasSignificantDifference = true;
                        break 2;
                    }
                }
            }

            if (!$hasSignificantDifference) {
                break;
            } else {
                $U = $U_new;
            }
        }

        $result = [];
        foreach ($U as $w) {
            $result[] = array_search(max($w), $w);
        }

        foreach ($ids as $index => $id) {
            $label = $result[$index];

            Candidate::where('id', $id)
                ->whereNull('label')
                ->update(['label' => $label]);
        }

        return $C;
    }

    public function get_result_df($id, $final_weights, $data, $columns)
    {
        $result = [];
        foreach ($final_weights as $w) {
            $result[] = array_search(max($w), $w);
        }

        return $result;

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

    public function predict($id) {
        $result1 = Candidate::select('author_id2', 'measure1', 'measure2', 'measure3', 'measure4')
                ->where('author_id1', $id)
                ->where('label', 1)
                ->distinct('author_id2')
                ->get()
                ->toArray();

        $result2 = Candidate::select('author_id1', 'measure1', 'measure2', 'measure3', 'measure4')
                ->where('author_id2', $id)
                ->where('label', 1)
                ->distinct('author_id1')
                ->get()
                ->toArray();

        $result = array_merge($result1, $result2);
        return $result;
    }
}
