<?php
    require_once(__DIR__ . '/../global.inc.php'); // это обвязка, она не важна

    function tweet_process($job, &$log)
    {
        $workload = unserialize($job->workload());

        try {
            $tweet = new TJ\Tweet\Processing\Tweet($workload);
            $tweet->process();
        } catch (TJ\Tweet\Exception\BaseException $e) {
            //
        }

        return true;
    }
