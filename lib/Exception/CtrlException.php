<?php
namespace Coercive\Utility\Router\Exception;

use Exception;

/**
 * CtrlException
 *
 * @package		Coercive\Utility\Router
 * @link		@link https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2017 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class CtrlException extends Exception {

	const CONTROLLER_PATTERN_ERROR = 'CtrlException::Pattern don\'t match ';
	const DEFAULT_CONTROLLER_ERROR = 'CtrlException::Can\'t load default ctrl ';

}