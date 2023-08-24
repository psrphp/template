<?php

declare(strict_types=1);

namespace PsrPHP\Template;

use Psr\SimpleCache\CacheInterface;
use SplPriorityQueue;

class Template
{
    protected $cache = null;
    protected $type_list = [];
    protected $path_list = [];
    protected $extends = [];

    protected $literals = [];
    protected $code = '';
    protected $data = [];
    protected $filename = '';

    public function __construct(
        CacheInterface $cache = null
    ) {
        $this->cache = $cache;

        $type_list = ['default'];
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            if (stripos($_SERVER['HTTP_USER_AGENT'], 'iphone')) {
                array_unshift($type_list, 'mobile');
                array_unshift($type_list, 'iphone');
            } elseif (stripos($_SERVER['HTTP_USER_AGENT'], 'android')) {
                array_unshift($type_list, 'mobile');
                array_unshift($type_list, 'android');
            } elseif (stripos($_SERVER['HTTP_USER_AGENT'], 'ipad')) {
                array_unshift($type_list, 'ipad');
            } else {
                array_unshift($type_list, 'pc');
            }
        }
        $this->type_list = $type_list;
    }

    public function addPath(string $name, string $path, $priority = 0): self
    {
        if (!isset($this->path_list[$name])) {
            $this->path_list[$name] = new SplPriorityQueue;
        }
        $this->path_list[$name]->insert($path, $priority);
        return $this;
    }

    public function extend(string $preg, callable $callback): self
    {
        $this->extends[$preg] = $callback;
        return $this;
    }

    public function assign($name, $value = null): self
    {
        if (is_array($name)) {
            $this->data = array_merge($this->data, $name);
        } else {
            $this->data[$name] = $value;
        }
        return $this;
    }

    public function renderFromFile(string $file, array $data = []): string
    {
        if ($data) {
            $this->assign($data);
        }

        $cache_key = $this->getCacheKey($file);

        if (!$this->cache || !$code = $this->cache->get($cache_key)) {
            $code = $this->parseString($this->getTplFileContent($file));
            if ($this->cache) {
                $this->cache->set($cache_key, $code);
            }
        }

        $this->code = $code;
        $this->filename = $file;
        return $this->render();
    }

    public function renderFromString(string $string, array $data = [], string $filename = ''): string
    {
        if ($data) {
            $this->assign($data);
        }

        $cache_key = $this->getCacheKey($filename ?: md5($string));

        if (!$this->cache || !$code = $this->cache->get($cache_key)) {
            $code = $this->parseString($string);
            if ($this->cache) {
                $this->cache->set($cache_key, $code);
            }
        }

        $this->code = $code;
        $this->filename = $filename;
        return $this->render();
    }

    private function getTplFile(string $tpl): ?string
    {
        list($file, $name) = explode('@', $tpl);
        if ($name && $file && isset($this->path_list[$name])) {
            foreach (clone $this->path_list[$name] as $path) {
                foreach ($this->type_list as $type) {
                    $fullname = $path . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file . '.php';
                    if (is_file($fullname)) {
                        return $fullname;
                    }
                }
            }
        }
        return null;
    }

    private function getCacheKey(string $name): string
    {
        return str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '_', 'tpl_' . $name);
    }

    private function parseString(string $string): string
    {
        $string = $this->buildLiteral($string);
        $string = $this->parseTag($string);
        $string = $this->parseLiteral($string);
        return $string;
    }

    private function getTplFileContent(string $tpl): string
    {
        if ($filename = $this->getTplFile($tpl)) {
            return file_get_contents($filename);
        }
        throw new NotFoundException('template [' . $tpl . '] is not found!');
    }

    private function buildLiteral(string $html): string
    {
        return preg_replace_callback(
            '/{literal}([\s\S]*){\/literal}/Ui',
            function ($matchs) {
                $key = '#' . md5($matchs[1]) . '#';
                $this->literals[$key] = $matchs[1];
                return $key;
            },
            $html
        );
    }

    private function parseLiteral(string $html): string
    {
        return str_replace(
            array_keys($this->literals),
            array_values($this->literals),
            $html
        );
    }

    private function parseTag(string $html): string
    {
        $tags = [
            '/\{(foreach|if|for|switch|while)\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php ' . $matchs[1] . ' (' . $matchs[2] . ') { ?>';
            },
            '/\{function\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php function ' . $matchs[1] . '{ ?>';
            },
            '/\{php\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<?php ' . $matchs[1] . '; ?>';
            },
            '/\{dump\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<pre><?php ob_start();var_dump(' . $matchs[1] . ');echo htmlspecialchars(ob_get_clean()); ?></pre>';
            },
            '/\{print\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<pre><?php echo htmlspecialchars(print_r(' . $matchs[1] . ', true)); ?></pre>';
            },
            '/\{echo\s+(.*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<?php echo ' . $matchs[1] . '; ?>';
            },
            '/\{case\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php case ' . $matchs[1] . ': ?>';
            },
            '/\{default\s*\}/Ui' => function ($matchs) {
                return '<?php default: ?>';
            },
            '/\{php\}/Ui' => function ($matchs) {
                return '<?php ';
            },
            '/\{\/php\}/Ui' => function ($matchs) {
                return ' ?>';
            },
            '/\{\/(foreach|if|for|function|switch|while)\}/Ui' => function ($matchs) {
                return '<?php } ?>';
            },
            '/\{\/(case|default)\}/Ui' => function ($matchs) {
                return '<?php break; ?>';
            },
            '/\{(elseif)\s+(.*)\}/Ui' => function ($matchs) {
                return '<?php }' . $matchs[1] . '(' . $matchs[2] . '){ ?>';
            },
            '/\{else\/?\}/Ui' => function ($matchs) {
                return '<?php }else{ ?>';
            },
            '/\{include\s*([\w\-_\.,@\/]*)\}/Ui' => function ($matchs) {
                $html = '';
                $tpls = explode(',', $matchs[1]);
                foreach ($tpls as $tpl) {
                    $html .= $this->getTplFileContent($tpl);
                }
                return $this->parseTag($this->buildLiteral($html));
            },
            '/\{(\$[^{}\'"]*)((\.[^{}\'"]+)+)\}/Ui' => function ($matchs) {
                return '<?php echo htmlspecialchars(' . $matchs[1] . substr(str_replace('.', '\'][\'', $matchs[2]), 2) . '\']' . '); ?>';
            },
            '/\{(\$[^{}]*)\}/Ui' => function ($matchs) {
                return '<?php echo htmlspecialchars(' . $matchs[1] . '); ?>';
            },
            '/\{:([^{}]*)\s*;?\s*\}/Ui' => function ($matchs) {
                return '<?php echo htmlspecialchars(' . $matchs[1] . '); ?>';
            },
            '/\?>[\s]*<\?php/is' => function ($matchs) {
                return '';
            },
        ];
        $tags = array_merge($tags, $this->extends);
        foreach ($tags as $preg => $callback) {
            $html = preg_replace_callback($preg, $callback, $html);
        }
        return $html;
    }

    private function render(): string
    {
        if (!strlen($this->code)) {
            return '';
        }
        ob_start();
        try {
            $__file__ = tempnam(sys_get_temp_dir(), 'tpl_' . $this->filename);
            file_put_contents($__file__, $this->code);
            extract($this->data);
            include $__file__;
            unlink($__file__);
            return ob_get_clean();
        } catch (\Throwable $th) {
            @unlink($__file__);
            ob_end_clean();
            throw $th;
        }
    }
}
