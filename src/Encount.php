<?php

namespace Encount;

use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Encount\Collector\EncountCollector;
use Exception;

class Encount
{
    use InstanceConfigTrait;

    protected $_defaultConfig = [
        'force' => false,
        'sender' => ['Encount.Mail'],
        'deny' => [
            'error' => [],
            'exception' => []
        ],
        'mail' => [
            'prefix' => '',
            'html' => true
        ]
    ];

    public function __construct()
    {
        $config = Configure::read('Error.encount');

        $encountConfig = [];
        if (!empty($config)) {
            $encountConfig = $config;
        }

        $this->setConfig($encountConfig, null, false);
    }

    public function execute($code, $description = null, $file = null, $line = null, $context = null)
    {
        $debug = Configure::read('debug');

        if ($this->getConfig('force') === false && $debug == true) {
            return;
        }

        if ($this->deny($code)) {
            return ;
        }

        $collector = new EncountCollector();
        $collector->build($code, $description, $file, $line, $context);

        foreach ($this->getConfig('sender') as $senderName) {
            $sender = $this->generateSender($senderName);
            $sender->send($this->getConfig(), $collector);
        }
    }

    private function deny($check)
    {
        $denyList = $this->getConfig('deny');

        if ($check instanceof Exception) {
            if (isset($denyList['exception'])) {
                foreach ($denyList['exception'] as $ex) {
                    if (is_a($check, $ex)) {
                        return true;
                    }
                }
            }
        } else {
            if (isset($denyList['error'])) {
                foreach ($denyList['error'] as $e) {
                    if ($check == $e) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * generate Encount Sender
     *
     * @access private
     * @author sakuragawa
     */
    private function generateSender($name)
    {
        $class = App::className($name, 'Sender');
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Encount sender "%s" was not found.', $class));
        }

        return new $class();
    }
}
