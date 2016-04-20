<?php
/**
 * Peanut\Migration
 *
 * @package    Peanut\Migration
 */
namespace Peanut\Migration;

/**
 * Migration Logger Class
 *
 * @author kohkimakimoto <kohki.makimoto@gmail.com>
 * @author Max <kwon@yejune.com>
 */
class Logger
{
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function write($msg, $prefix = null, $level = 'info')
    {
        if (!$this->config->get('log', true))
        {
            return;
        }

        if ($prefix)
        {
            $prefix .= ' ';
        }

        if ($level == 'debug')
        {
            if ($this->config->get('debug'))
            {
                if ($this->config->get('colors'))
                {
                    echo pack('c',0x1B)."[0;34m"."DEBUG >> ".$msg.pack('c',0x1B)."[0m\n";
                }
                else
                {
                    echo "DEBUG >> ".$msg."\n";
                }
            }
        }
        elseif ($level == 'error')
        {
            if ($this->config->get('colors'))
            {
                echo pack('c',0x1B)."[0;31m".pack('c',0x1B)."[43m"."ERROR >> ".$msg.pack('c',0x1B)."[0m\n";
            }
            else
            {
                echo $prefix.$msg."\n";
            }
        }
        else
        {
            if ($this->config->get('colors'))
            {
                echo pack('c',0x1B)."[0;32m".$prefix.pack('c',0x1B)."[0m".$msg."\n";
            }
            else
            {
                echo $prefix.$msg."\n";
            }
        }
    }
}
