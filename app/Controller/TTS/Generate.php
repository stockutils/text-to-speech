<?php
/**
 * Created by: MinutePHP framework
 */
namespace App\Controller\TTS {

    use Minute\Error\TTSError;
    use Minute\Event\AuthEvent;
    use Minute\Http\Browser;
    use Minute\Http\HttpRequestEx;
    use Minute\Session\Session;
    use Minute\TTS\TTSManager;

    class Generate {
        /**
         * @var TTSManager
         */
        private $manager;
        /**
         * @var Session
         */
        private $session;
        /**
         * @var Browser
         */
        private $browser;

        /**
         * Generate constructor.
         *
         * @param TTSManager $manager
         * @param Session $session
         * @param Browser $browser
         */
        public function __construct(TTSManager $manager, Session $session, Browser $browser) {
            $this->manager = $manager;
            $this->session = $session;
            $this->browser = $browser;
        }

        public function index(HttpRequestEx $request) {
            $id   = $request->getParameter('voice');
            $text = $request->getParameter('text');

            if (!empty($id) && !empty($text)) {
                if ($voices = $this->manager->getVoices()) {
                    foreach ($voices as $item) {
                        if ($item['Id'] === $id) {
                            $voice = $item;
                            break;
                        }
                    }

                    if (!empty($voice)) {
                        $event = new AuthEvent($voice['Level']);
                        $this->session->checkAccess($event);

                        if ($event->isAuthorized()) {
                            if ($url = $this->manager->speak($voice['Id'], $text)) {
                                return json_encode(['url' => $url]);
                            }
                        } else {
                            throw new TTSError("$id voice is only available to {$voice['level']} account users. Please upgrade your account to get access to this voice!");
                        }
                    }
                }

                throw new TTSError("Voice: $id is not available");
            }

            throw new TTSError("Voice and text are required");
        }
    }
}