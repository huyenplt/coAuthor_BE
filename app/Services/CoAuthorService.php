<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Facades\DB;
use App\Models\PaperAuthor;
use App\Models\CoAuthor;

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
        $candidates = CoAuthor::select(
            'author_id1',
            'author_id2',
        )
            ->where('paper_year', '<', $split_year)
            ->distinct()
            ->get();

        $repeatedAuthor1 = CoAuthor::select('author_id1')
            ->where('paper_year', '<', $split_year)
            ->groupBy('author_id1')
            ->havingRaw('COUNT(author_id1) > 1')
            ->get();

        foreach ($repeatedAuthor1 as $item) {
            $author1ids = CoAuthor::select('author_id2')
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

        $this->labeledCandidates($split_year);

        return $candidates;
    }

    public function labeledCandidates($split_year)
    {
        DB::table('candidates')
            ->join('co_author', function ($join) {
                $join->on('candidates.author_id1', '=', 'co_author.author_id1')
                    ->on('candidates.author_id2', '=', 'co_author.author_id2');
            })
            ->where('co_author.paper_year', '>=', $split_year)
            ->update(['candidates.label' => 1]);
    }

    public function calculateMeasures($measures)
    {
        foreach ($measures as $measure) {
            if (in_array("Common Neighbor (CN)", $measure)) {
                $this->calculateCN();
            }
            // if (in_array("Adamic-Adar (AA)", $measure)) {
            //     $this->calculateAA();
            // }
            // if (in_array("Jaccard Coefficient (JC)", $measure)) {
            //     $this->calculateJC();
            // }
            // if (in_array("Resource Allocation (RA)", $measure)) {
            //     $this->calculateRA();
            // }
        }
    }

    public function calculateCN()
    {
        $data = $this->getCommonNeighbor();
        $candidates = Candidate::all();
        foreach ($candidates as $index => $candidate) {
            $candidate->measure1 = count($data[$index]);
            $candidate->save();
        }
    }

    public function calculateAA()
    {
        $data = $this->getCommonNeighbor();
        return $data;
        $candidates = Candidate::all();
        foreach ($candidates as $index => $candidate) {
            if (count($data[$index]) > 0) {
                $total = 0;
                foreach($data[$index] as $neighbor) {
                    $count = Candidate::where('author_id1', $neighbor)->count();
                    $total += 1/log($count);
                }
                $candidate->measure1 = $total;
            }
            else $candidate->measure1 = 0;
            $candidate->save();
        }
    }

    public function calculateJC()
    {
    }

    public function calculateRA()
    {
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
}
