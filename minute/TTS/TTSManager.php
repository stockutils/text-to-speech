<?php
/**
 * User: Sanchit <dev@minutephp.com>
 * Date: 11/23/2016
 * Time: 6:24 AM
 */
namespace Minute\TTS {

    use Aws\Polly\PollyClient;
    use GuzzleHttp\Psr7\Stream;
    use Illuminate\Support\Str;
    use Minute\Aws\Client;
    use Minute\Cache\QCache;
    use Minute\Config\Config;
    use Minute\Error\AwsError;
    use Minute\Event\Dispatcher;
    use Minute\Event\UserUploadEvent;
    use Minute\Session\Session;

    class TTSManager {
        const key = 'tts';
        /**
         * @var PollyClient
         */
        protected $polly;
        /**
         * @var Config
         */
        private $config;
        /**
         * @var QCache
         */
        private $cache;
        /**
         * @var Client
         */
        private $client;
        /**
         * @var Dispatcher
         */
        private $dispatcher;
        /**
         * @var Session
         */
        private $session;

        /**
         * TTSManager constructor.
         *
         * @param Config $config
         * @param QCache $cache
         * @param Client $client
         *
         * @param Dispatcher $dispatcher
         *
         * @param Session $session
         *
         * @throws AwsError
         */
        public function __construct(Config $config, QCache $cache, Client $client, Dispatcher $dispatcher, Session $session) {
            $this->config     = $config;
            $this->cache      = $cache;
            $this->client     = $client;
            $this->dispatcher = $dispatcher;
            $this->session    = $session;

            if ($credentials = $this->config->get(self::key . '/polly')) {
                $this->polly = $this->client->getClient('polly', array_merge($credentials, ['class' => PollyClient::class]));
            } else {
                throw new AwsError("Polly credentials are missing");
            }
        }

        public function getVoices() {
            $voices = $this->cache->get("tts-voices", function () {
                $result = $this->polly->describeVoices();
                $voices = $result->get('Voices');

                if ($access = $this->config->get(self::key . '/access', ['trial' => ['Joanna']])) {
                    foreach ($voices as $index => $voice) {
                        foreach ($access as $level => $includes) {
                            if (in_array($voice['Id'], $includes)) {
                                $voices[$index]['Level'] = $level;
                            }
                        }
                    }

                    if ($defaultLevel = $this->config->get(self::key . '/access', 'power')) {
                        foreach ($voices as $index => $voice) {
                            if (empty($voice['Level'])) {
                                $voices[$index]['Level'] = $defaultLevel;
                            }
                        }
                    }
                }

                uasort($voices, function ($a, $b) {
                    $weight = function ($v) {
                        return $v['LanguageCode'] == 'en-US' ? 'A' : ($v['LanguageCode'] == 'en-GB' ? 'B' : (preg_match('/^en/', $v['LanguageCode']) ? 'C' : $v['LanguageCode']));
                    };

                    return $weight($a) <=> $weight($b);
                });

                return $voices;
            }, 86400);

            return $voices;
        }

        public function speak($voiceId, $text) {
            $result = $this->polly->synthesizeSpeech(['OutputFormat' => 'mp3', 'SampleRate' => '22050', 'Text' => $text, 'TextType' => 'text', 'VoiceId' => $voiceId]);

            /** @var Stream $stream */
            $stream = $result->get('AudioStream');
            $output = $stream->getContents();

            $filename = sprintf('%s.%s', md5("voice-$voiceId-$text"), 'mp3');
            $event = new UserUploadEvent($this->session->getLoggedInUserId(), $output, $filename, 'data');
            $this->dispatcher->fire(UserUploadEvent::USER_UPLOAD_FILE, $event);

            if ($url = $event->getUrl()) {
                return $url;
            }

            return null;
        }
    }
}