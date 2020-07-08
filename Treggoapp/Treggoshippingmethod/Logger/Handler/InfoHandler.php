<?php
/**
 * Created by PhpStorm.
 * User: matiasdameno
 * Date: 28/06/2020
 * Time: 16:08
 */

namespace Treggoapp\Treggoshippingmethod\Logger\Handler;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger as MonologLogger;

/**
 * Class InfoHandler
 */
class InfoHandler extends BaseHandler
{
    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = MonologLogger::INFO;

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = 'var/log/treggoshippingmethod/info.log';
}
