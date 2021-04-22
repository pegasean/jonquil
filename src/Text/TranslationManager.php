<?php

declare(strict_types=1);

namespace Jonquil\Text;

use Jonquil\Type\Map;
use Exception;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class TranslationManager
 * @package Jonquil\Providers
 */
class TranslationManager
{
    const INDENT_LENGTH = 4;

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'directory_not_writable'    => 'The directory "%s" is not writable',
        'unsupported_language'      => 'Unsupported language "%s"',
    ];

    /**
     * @var Map
     */
    protected $languages;

    /**
     * @var string
     */
    protected $langDir;

    /**
     * @var string
     */
    protected $backupDir;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * Initializes the class properties.
     *
     * @param Translator $translator
     * @param Map $languages
     * @param string $backupDir
     * @throws Exception
     */
    public function __construct(
        Translator $translator,
        Map $languages,
        string $backupDir
    ) {
        $langDir = $translator->getDirectory();
        $backupDir = rtrim($backupDir, '/') . '/';
        if (!is_writable($langDir)) {
            throw new Exception(
                sprintf(self::$errors['directory_not_writable'], $langDir)
            );
        } elseif (!is_writable($backupDir)) {
            throw new InvalidArgumentException(
                sprintf(self::$errors['directory_not_writable'], $backupDir)
            );
        }
        $backupDir = $backupDir . 'translations/';
        if (!is_readable($backupDir)) {
            mkdir($backupDir, 0777);
        }
        $this->languages = $languages;
        $this->langDir = $langDir;
        $this->backupDir = $backupDir;
        $this->translator = $translator;
    }

    /**
     * @param string $language
     * @return bool
     */
    public function isSupportedLanguage(string $language): bool
    {
        return $this->languages->has([$language]);
    }

    /**
     * @param array $directories
     * @param string $regex
     * @param bool $sort
     * @return array
     */
    public function extractKeys(
        array $directories,
        string $regex,
        bool $sort = true
    ): array {
        $extractedKeys = [];

        foreach ($directories as $directory) {
            $dirIterator = new RecursiveDirectoryIterator($directory);
            $files = new RecursiveIteratorIterator($dirIterator);
            foreach ($files as $file) {
                $file = (string) $file;
                $extension = pathinfo($file, PATHINFO_EXTENSION);
                if ($extension !== 'php') {
                    continue;
                }
                $matches = [];
                $content = file_get_contents($file);
                preg_match_all($regex, $content, $matches, PREG_PATTERN_ORDER);
                for ($i = 1; $i < count($matches); $i++) {
                    foreach ($matches[$i] as $key) {
                        if (!empty($key) && (strpos($key, '.') !== false)
                            && !array_key_exists($key, $extractedKeys)) {
                            $extractedKeys[$key] = '';
                        }
                    }
                }
            }
        }

        if ($sort) {
            ksort($extractedKeys, SORT_STRING);
        }

        return $extractedKeys;
    }

    /**
     * @param string $language
     * @param Map $dictionary
     * @param bool $saveBackup
     * @throws Exception
     */
    public function saveDictionary(
        string $language,
        Map $dictionary,
        bool $saveBackup = true
    ) {
        if (!$this->isSupportedLanguage($language)) {
            throw new InvalidArgumentException(
                sprintf(self::$errors['unsupported_language'], $language)
            );
        }
        if ($saveBackup) {
            $this->saveDictionaryBackup($language, $dictionary);
        }
        $directory = $this->langDir . $language;
        $this->deleteDirectory($directory);
        mkdir($directory, 0777);
        foreach ($dictionary->getKeys() as $key) {
            $file = $directory . '/' . $key . '.php';
            $rules = $dictionary->get($key, []);
            $this->saveFile($file, $rules);
        }
        $this->translator->deleteFromCache($language);
    }

    /*--------------------------------------------------------------------*/

    /**
     * @param string $language
     * @param Map $dictionary
     */
    protected function saveDictionaryBackup(
        string $language,
        Map $dictionary
    ) {
        $directory = $this->backupDir . $language;
        if (!is_readable($directory)) {
            mkdir($directory, 0777);
        }

        $file = $directory . '/' . date('Ymd_His') . '.bak.php';
        $this->saveFile($file, $dictionary->toArray());
    }

    /**
     * @param string $file
     * @param array $rules
     */
    protected function saveFile(string $file, array $rules)
    {
        $content = '<?php' . str_repeat(PHP_EOL, 2)
            . 'return [' . PHP_EOL;
        $content .= $this->exportTranslationRules($rules);
        $content .= '];' . str_repeat(PHP_EOL, 2);
        $content .= '// -- End of file' . PHP_EOL;

        file_put_contents($file, $content);
    }

    /**
     * @param array $rules
     * @param int $keyOffset
     * @return string
     */
    protected function exportTranslationRules(
        array $rules,
        $keyOffset = self::INDENT_LENGTH
    ): string {
        $keyPadLength = 0;
        foreach ($rules as $key => $value) {
            if (strlen($key) > $keyPadLength) {
                $keyPadLength = strlen($key);
            }
        }
        // Increment the right padding length (to include the quotes enclosing
        // the key and an extra space character before the separator) and
        // round it up to the next multiple of the indentation length.
        $keyPadLength += 3;
        $keyPadLength = intval(
            ceil($keyPadLength / self::INDENT_LENGTH) * self::INDENT_LENGTH
        );

        $content = '';

        foreach ($rules as $key => $value) {
            $content .= str_repeat(' ', $keyOffset);
            $content .= str_pad("'" . $key . "'", $keyPadLength, ' ') . '=> ';
            if (is_array($value)) {
                $value = $this->exportTranslationRules(
                    $value,
                    $keyOffset + self::INDENT_LENGTH
                );
                $content .= '[' . PHP_EOL . $value;
                $content .= str_repeat(' ', $keyOffset). '],';
            } else {
                $value = stripslashes($value);
                $content .= "'" . addslashes($value) . "',";
            }

            $content .= PHP_EOL;
        }

        return $content;
    }

    /**
     * Deletes a directory.
     *
     * @param string $directory
     */
    protected function deleteDirectory(string $directory)
    {
        $directory = rtrim($directory, '/');
        foreach (glob($directory . '/*') as $file) {
            if (is_dir($file)) {
                $this->deleteDirectory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($directory);
    }
}

// -- End of file
