<?php
/**
 * Created by: MinutePHP framework
 */
namespace App\Controller\TTS {

    use Minute\Event\AuthEvent;
    use Minute\Session\Session;
    use Minute\TTS\TTSManager;

    class Voices {
        /**
         * @var TTSManager
         */
        private $manager;
        /**
         * @var Session
         */
        private $session;

        /**
         * Voices constructor.
         *
         * @param TTSManager $manager
         * @param Session $session
         */
        public function __construct(TTSManager $manager, Session $session) {
            $this->manager = $manager;
            $this->session = $session;
        }

        public function index() {
            $voices = $this->manager->getVoices();

            foreach ($voices as $voice) {
                $authorized = true;

                if (!empty($voice['Level'])) {
                    $event = new AuthEvent($voice['Level'] ?? 'trial');
                    $this->session->checkAccess($event);
                    $authorized = $event->isAuthorized();
                }

                $results['voices'][] = array_merge($voice, $authorized ? [] : ['Name' => sprintf('%s (%s account only)', $voice['Name'], $voice['Level'])]);
            }

            return json_encode(!empty($results) ? $results : [], JSON_PRETTY_PRINT);
        }
    }
}