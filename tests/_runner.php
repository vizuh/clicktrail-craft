<?php

declare(strict_types=1);

/**
 * Standalone assert runner - no Craft runtime needed. Pattern of
 * clicktrail-php/tests/_runner.php. Craft::$app->session is stubbed; the SDK
 * sources are read from /sdk/src (override with CLICKTRAIL_SDK_SRC):
 *
 *   podman run --rm --network none -v "$PWD":/app -v "$PWD/../clicktrail-php":/sdk:ro \
 *     -w /app php:8.3-cli php tests/_runner.php
 */

namespace {
    // Minimal stand-in for Craft's facade: only what ConsentGate::storedSnapshot() uses.
    class Craft
    {
        public static object $app;

        /** @var string[] */
        public static array $warnings = [];

        public static function warning(string $message, string $category = 'application'): void
        {
            self::$warnings[] = $message;
        }
    }

    final class FakeSession
    {
        /** @param array<string, mixed> $data */
        public function __construct(public bool $active = true, public array $data = [])
        {
        }

        public function getIsActive(): bool
        {
            return $this->active;
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function set(string $key, mixed $value): void
        {
            $this->data[$key] = $value;
        }
    }

    // Yii/Craft turn PHP warnings into exceptions; mirror that so a warning
    // inside the decode path cannot pass silently here.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    $sdkSrc = rtrim((string) (getenv('CLICKTRAIL_SDK_SRC') ?: '/sdk/src'), '/');
    spl_autoload_register(static function (string $class) use ($sdkSrc): void {
        $map = ['ClickTrail\\Craft\\' => dirname(__DIR__) . '/src/', 'ClickTrail\\' => $sdkSrc . '/'];
        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $path = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require $path;
                }

                return;
            }
        }
    });

    function check(bool $cond, string $msg): void
    {
        if (!$cond) {
            fwrite(STDERR, "FAIL: $msg\n");
            exit(1);
        }
    }

    function stored(mixed $value, bool $active = true): ?\ClickTrail\Consent\ConsentSnapshot
    {
        $data = $value === null ? [] : [\ClickTrail\Craft\Services\ConsentGate::SESSION_SNAPSHOT_KEY => $value];
        Craft::$app = (object) ['session' => new FakeSession($active, $data)];

        return \ClickTrail\Craft\Services\ConsentGate::storedSnapshot();
    }

    // C1 a snapshot persisted by resolve() (toJson) decodes back intact
    $snapshot = new \ClickTrail\Consent\ConsentSnapshot(
        'custom',
        '2026-11-10T09:00:00.000Z',
        \ClickTrail\Consent\ConsentValue::Granted,
        \ClickTrail\Consent\ConsentValue::Granted,
        \ClickTrail\Consent\ConsentValue::Denied,
        \ClickTrail\Consent\ConsentValue::Unknown,
        \ClickTrail\Consent\ConsentValue::Denied,
        'v3',
        'r-1',
        ['analytics'],
    );
    $decoded = stored($snapshot->toJson());
    check($decoded !== null, 'C1 stored snapshot decodes');
    check($decoded->toArray() === $snapshot->toArray(), 'C1 round trip preserves every field');
    check(\ClickTrail\Consent\ConsentBehavior::can($decoded, \ClickTrail\Consent\ConsentSnapshot::CAP_ANALYTICS), 'C1 granted analytics survives');
    check(!\ClickTrail\Consent\ConsentBehavior::can($decoded, \ClickTrail\Consent\ConsentSnapshot::CAP_ADVERTISING_STORAGE), 'C1 denied advertising survives');

    // C2 malformed / non-object JSON degrades to no snapshot
    foreach (['{bad', '', 'null', '"granted"', '42', 'true', '[]'] as $bad) {
        check(stored($bad) === null, 'C2 malformed degrades: ' . var_export($bad, true));
    }

    // C3 structurally wrong values degrade to no snapshot instead of throwing
    check(stored('{"analytics_storage":["granted"]}') === null, 'C3 array consent value degrades');
    check(stored('{"raw_category_ids":[["x"]]}') === null, 'C3 nested category ids degrade');
    check(stored(['analytics_storage' => 'granted']) === null, 'C3 non-string session value degrades');
    check(count(Craft::$warnings) === 2, 'C3 invalid decodes are logged');

    // C4 missing keys fail closed (unknown), never granted
    $partial = stored('{"source":"custom"}');
    check($partial !== null && !\ClickTrail\Consent\ConsentBehavior::can($partial, \ClickTrail\Consent\ConsentSnapshot::CAP_ANALYTICS), 'C4 missing signal stays unknown');

    // C5 no session / nothing stored -> no snapshot
    check(stored($snapshot->toJson(), false) === null, 'C5 inactive session');
    check(stored(null) === null, 'C5 nothing stored');

    echo "CRAFT CONSENT ASSERTIONS PASSED\n";
}
