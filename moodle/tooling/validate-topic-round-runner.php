<?php
/** Standalone runner guard test; does not load Moodle, use credentials, or send mail. */
namespace local_web3talents\local {
    class applicant_service {
        public static function activation_emails_enabled(): bool {
            return in_array('--enabled', $GLOBALS['argv'], true);
        }
    }
    class topic_round_service {
        public static int $calls = 0;
        public static function finalize_due_rounds(): int {
            self::$calls++;
            return 2;
        }
    }
}
namespace {
    $path = $argv[1] ?? __DIR__ . '/../plugins/local_web3talents/cli/finalize_topic_rounds.php';
    $source = file_get_contents($path);
    $bootstrap = "require(__DIR__ . '/../../../config.php');";
    if (substr_count($source, $bootstrap) !== 1) {
        throw new \RuntimeException('Unexpected runner bootstrap; refusing to load it.');
    }
    $source = str_replace($bootstrap, '', $source);
    $enabled = \local_web3talents\local\applicant_service::activation_emails_enabled();
    register_shutdown_function(function() use ($enabled): void {
        $calls = \local_web3talents\local\topic_round_service::$calls;
        if ($calls !== ($enabled ? 1 : 0)) {
            fwrite(STDERR, "FAIL: incorrect finalisation call count.\n");
            exit(1);
        }
        echo 'PASS: ' . ($enabled ? 'released runner invokes finalisation once' : 'paused runner makes no changes') . "\n";
    });
    eval('?>' . $source);
}
