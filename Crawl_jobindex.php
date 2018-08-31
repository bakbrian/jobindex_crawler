<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Crawl_jobindex extends Command
{
    protected $signature = 'Crawl_jobindex';
    protected $description = 'Crawl_jobindex';
    public $sleep = 5; // Second to sleep between curl requests

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        echo "\n";
        echo "Sending requests to jobindex.dk\n";
        foreach(\App\Job_index_queries::get() AS $query)
        {
            echo "Query: " . $query->name . "\n";
            $ch = curl_init($query->query);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, "Firefox/3.0.6");
            $site = curl_exec($ch);
            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($response == 200)
            {
                echo "Response: OK\n";
                file_put_contents("jobindex.dat", $site);
            } else
            {
                echo "Something is wrong\n";
                die();
            }
            $site = file_get_contents("jobindex.dat");
            $dom = new \DOMDocument;
            $dom->preserveWhiteSpace = false;
            @$dom->loadHTML($site);
            $jobs = [];
            $i = 0;
            // De store opslag på siden
            foreach ($dom->getElementsByTagName('div') as $item)
            {
                if ($item->getAttribute('class') == "PaidJob")
                {
                    $i++;
                    $tmp_dom = new \DOMDocument;
                    $tmp_dom->preserveWhiteSpace = false;
                    @$tmp_dom->loadHTML($dom->saveHTML($item));
                    $b = 0;
                    foreach ($tmp_dom->getElementsByTagName('a') as $item)
                    {
                        $b++;
                        if ($b == 2)
                        {
                            $jobs[$i]['title'] = trim(utf8_decode($item->nodeValue));
                            $jobs[$i]['jobindex_link'] = $item->getAttribute('href');
                        }
                    }
                }
            }
            // De små opslag på siden
            foreach ($dom->getElementsByTagName('div') as $item)
            {
                if ($item->getAttribute('class') == "jix_robotjob")
                {
                    $i++;
                    $tmp_dom = new \DOMDocument;
                    $tmp_dom->preserveWhiteSpace = false;
                    @$tmp_dom->loadHTML($dom->saveHTML($item));
                    $b = 0;
                    foreach ($tmp_dom->getElementsByTagName('a') as $item)
                    {
                        $b++;
                        if ($b == 1)
                        {
                            $jobs[$i]['title'] = trim(utf8_decode($item->nodeValue));
                            $jobs[$i]['jobindex_link'] = $item->getAttribute('href');
                        }
                    }
                }
            }
            foreach($jobs AS $job)
            {
                // echo "Job title: " . $job['title'] . "\n";
                // echo "Link to job: " . $job['jobindex_link'] . "\n";
                if (\App\Jobs::where('link', $job['jobindex_link'])->withTrashed()->count() == 0)
                {
                    echo "+";
                    $newJob = new \App\Jobs;
                    $newJob->title = $job['title'];
                    $newJob->link = $job['jobindex_link'];
                    $newJob->job_index_queries_id = $query->id;
                    $newJob->save();
                } else
                {
                    echo ".";
                }
            }
            echo "\nSleeping ". $this->sleep . " seconds\n\n";
            sleep($this->sleep);
        } // foreach queries
        echo "Done\n";
    } // handle
} // class
