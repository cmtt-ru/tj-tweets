<?php
    require_once __DIR__ . '/vendor/autoload.php';

    $daemon = new Firehed\ProcessControl\Daemon;

    declare (ticks=1);

    $daemon->setUser('root')
    ->setPidFileLocation('/var/run/tj-twitter-users.pid')
    ->setStdoutFileLocation(sys_get_temp_dir() . '/tj-twitter-users.log')
    ->setStdErrFileLocation(sys_get_temp_dir() . '/tj-twitter-users-err.log')
    ->setProcessName('tj-twitter-users')
    ->autoRun();

    define('TWITTER_CONSUMER_KEY', 'app key'); // changeme
    define('TWITTER_CONSUMER_SECRET', 'app secret'); // changeme

    class FilterTrackConsumer extends OauthPhirehose
    {
        private $_track = [];
        private $_trackUpdate = 0;

        public function enqueueStatus($status)
        {
            $data = json_decode($status, true);

            if (is_array($data) && isset($data['user']['screen_name'])) {
                Gear::doBackground('tweet_process', serialize($data)); // this is sending to background worker (gearman, rabbitmq)
                // see tweet_process.php

                echo date('d.m H:i:s') . " +\n";
            } else {
                echo date('d.m H:i:s') . $status . "\n";
            }
        }

        public function checkFilterPredicates()
        {
            if (empty($this->_track) || $this->_trackUpdate < time() - 300) {
                $this->_track = $this->getTrackIds();
                $this->_trackUpdate = time();

                echo date('d.m H:i:s') . " . " . count($this->_track) . "\n";
            }

            $size = memory_get_usage();
            $unit = array('b','kb','mb','gb','tb','pb');
            echo @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i] . "\n";

            $this->setFollow($this->_track);
        }

        public function getTrackIds()
        {
            try {
                // Тут из базы берём id чуваков, которых отслеживаем
                $tweople = \DB\Twitter\TweopleList::select('twitter_tweople_lists.tweople_id')
                    ->distinct()
                    ->inner_join('twitter_lists', ['twitter_tweople_lists.list_id', '=', 'twitter_lists.id'])
                    ->where('twitter_lists.is_active', 1)
                    ->find_array();

                $result = [];
                if (count($tweople)) {
                    foreach ($tweople as $tw) {
                        $result[] = intval($tw['tweople_id']);
                    }

                    $result = array_unique($result);

                    if (count($result) > 5000) {
                        \Log::addError('TWTR: There is 5000 people max');
                        $result = array_slice($result, 0, 5000);
                    }
                }
                unset($tweople);

                return $result;
            } catch (Exception $e) {
                \Log::addException($e);
            }

            return [];
        }
    }

    $sc = new FilterTrackConsumer('user token', 'user secert key', Phirehose::METHOD_FILTER);
    $sc->setFollow($sc->getTrackIds());
    $sc->consume();
