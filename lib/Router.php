<?php
namespace Coercive\Utility\Router;

use Coercive\Utility\Globals\Globals;
use Coercive\Utility\Router\Exception\RouterException;

/**
 * Router
 * PHP Version 	7
 *
 * (FR) La simplicité est la sophistication suprême.
 * Léonard de Vinci
 *
 * @package		Coercive\Utility\Router
 * @link		@link https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2018 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Router {

    /** @var string INPUT_SERVER */
    private
        $_REQUEST_SCHEME,
        $_DOCUMENT_ROOT,
        $_HTTP_HOST,
        $_REQUEST_METHOD,
        $_HTTP_USER_AGENT,
        $_REQUEST_URI,
        $_QUERY_STRING;

    /** @var Globals */
    private $_oGlobals = null;

    /** @var Parser */
    private $_oParser = null;

    /** @var array From Parser */
    private $_aRoutes = [];

    /** @var string Current URI */
    private $_sUrl = null;

    /** @var array Not rewritten GET params (after '?' in url) */
    private $_aParamsGet = [];

    /** @var array Rewritten route GET params */
    private $_aRouteParamsGet = [];

    /** @var array Overload params for switch url lang */
    private $_aTranslateRouteParams = [];

    /** @var string Current matched route ID */
    private $_sId = null;

    /** @var string Current matched route LANG */
    private $_sLang = null;

    /** @var string Current matched route CONTROLLER */
    private $_sController = null;

    /** @var bool is an ajax request */
    private $_bAjaxDemand = false;

    /** @var string request type accepted */
    private $_sHttpAccept = null;

    /**
     * INIT INPUT SERVER
     *
     * @return void
     */
    private function _initInputServer() {

        # INPUT_SERVER
        $this->_REQUEST_SCHEME = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $this->_DOCUMENT_ROOT = (string) filter_input(INPUT_SERVER, 'DOCUMENT_ROOT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->_HTTP_HOST = (string) filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->_REQUEST_METHOD = (string) filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->_HTTP_USER_AGENT = (string) filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        # INPUT SERVER REQUEST
        $this->_REQUEST_URI = (string) trim(urldecode(filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL)), '/');
        $this->_QUERY_STRING = (string) urldecode(filter_input(INPUT_SERVER, 'QUERY_STRING', FILTER_SANITIZE_URL));

    }

    /**
     * INIT PARAM QUERY STRING
     *
     * @return void
     */
    private function _initQueryString() {

        # Array of params
        parse_str($this->_QUERY_STRING, $aGet);
        $this->_aParamsGet = $aGet;

    }

    /**
     * INIT AJAX REQUEST DETECTION
     *
     * @return void
     */
    private function _initAjaxDetection() {

        /** @var bool */
        $this->_bAjaxDemand = filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH', FILTER_SANITIZE_FULL_SPECIAL_CHARS) === 'XMLHttpRequest';

        /** @var string $sAccept */
        $sAccept = (string) filter_input(INPUT_SERVER, 'HTTP_ACCEPT', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (false !== strpos($sAccept, 'text/html')) {
            $this->_sHttpAccept = 'html';
        }
        elseif (false !== strpos($sAccept, 'application/json')) {
            $this->_sHttpAccept = 'json';
        }
        elseif (false !== strpos($sAccept, 'application/xml')) {
            $this->_sHttpAccept = 'xml';
        }
        else {
            $this->_sHttpAccept = 'html';
        }

    }

    /**
     * Start process : launch of routes detection
     *
     * @return void
     * @throws RouterException
     */
    private function _run() {

        # SECURITY
        if(!$this->_aRoutes) { throw new RouterException('Router cant start. No routes avialable.'); }

        # PREPARE EACH ROUTE
        foreach($this->_aRoutes as $sId => $aItem) {

            # EACH LANGUAGE
            foreach($aItem['routes'] as $sLang => $aDatas) {

                # MATCH
                if($this->_match($aDatas['regex'])) {
                    $this->_sId = $sId;
                    $this->_sLang = $sLang;
                    $this->_sController = $aItem['controller'];
                    return;
                }

            }
        }

    }

    /**
     * MATCH URL / ROUTE
     *
     * @param string $sPath
     * @return bool
     */
    private function _match($sPath) {
        if(!preg_match("`^$sPath$`i", $this->_sUrl, $aMatches)) { return false; }
        $aIntKeys = array_filter(array_keys($aMatches), 'is_numeric');
        $this->_aRouteParamsGet = array_diff_key($aMatches, array_flip($aIntKeys));
        return true;
    }

    /**
     * INIT SUPER GLOBAL $_GET MERGE
     *
     * @return void
     */
    private function _initSuperGlobalGET() {
        $this->_aParamsGet = $this->_oGlobals->autoFilterManualVar($this->_aParamsGet);
        $this->_aRouteParamsGet = $this->_oGlobals->autoFilterManualVar($this->_aRouteParamsGet);
        $_GET = array_replace_recursive([], $_GET, $this->_aParamsGet, $this->_aRouteParamsGet);
    }

    /**
     * REWRITE URL WITH PARAMS
     *
     * @param string $sId
     * @param string $sLang
     * @param array $aInjectedParams
     * @return string
     * @throws RouterException
     */
    private function _rewriteUrl($sId, $sLang, $aInjectedParams) {

        $sUrl = $this->_aRoutes[$sId]['routes'][$sLang]['original'];

        foreach ($this->_aRoutes[$sId]['routes'][$sLang]['params'] as $iKey => $aParam) {

            # EXIST
            if (isset($aInjectedParams[$aParam['name']]) && !is_bool($aInjectedParams[$aParam['name']]) && '' !== $aInjectedParams[$aParam['name']]) {

                # PARAM VAUE
                $sValue = $aInjectedParams[$aParam['name']];
                if(!preg_match("`$aParam[regex]`i", $sValue)) {
                    throw new RouterException("Route param regex not match : $aParam[name], regex : $aParam[regex], value : $sValue");
                }

                # TRIM OPTIONAL BRACKETS
                if($aParam['optional']) {
                    $sUrl = str_replace($aParam['optional'], trim($aParam['optional'], '[]'), $sUrl);
                }

                # INJECT PARAM
                $sUrl = str_replace($aParam['subject'], $sValue, $sUrl);
            }

            # OPTIONAL EMPTY PARAM
            elseif(!empty($aParam['optional'])) {
                $sUrl = str_replace($aParam['optional'], '', $sUrl);
                continue;
            }

            # FORGOTTEN PARAM
            else {
                throw new RouterException("Route required param not found for switch : $aParam[name]");
            }

        }

        return $sUrl;

    }

    /**
     * Init
     * Coercive Router constructor.
     *
     * @param Parser $oParser
     */
    public function __construct(Parser $oParser) {

        # Bind user routes
        $this->_oParser = $oParser;
        $this->_aRoutes = $oParser->get();
        $this->_oGlobals = new Globals;

        # INPUT SERVER
        $this->_initInputServer();

        # AJAX
        $this->_initAjaxDetection();

        # PARAMS GET
        $this->_initQueryString();

        # URL
        $this->_sUrl = $oParser->clean($this->_REQUEST_URI);

        # RUN
        $this->_run();

        # SET _GET
        $this->_initSuperGlobalGET();
    }

    /** GETTERS @return string ID - LANG - CONTROLLER */
    public function getId() { return $this->_sId; }
    public function getLang() { return $this->_sLang; }
    public function getController() { return $this->_sController; }

    /** GETTERS SPECIAL @return string|array|bool */
    public function getHost() { return $this->_HTTP_HOST; }
    public function getAccessMode() { return $this->_REQUEST_METHOD; }
    public function getHttpMode() { return $this->_REQUEST_SCHEME; }
    public function getCurrentRouteParams() { return $this->_aRouteParamsGet; }
    public function getTranslateRouteParams() { return $this->_aTranslateRouteParams; }
    public function isAjaxRequest() { return $this->_bAjaxDemand; }
    public function setAjaxRequest($bBool) { $this->_bAjaxDemand = $bBool; return $this; }
    public function getHttpAccept() { return $this->_sHttpAccept; }
    public function getCurrentURL($bFullUrl = false) { return $bFullUrl ? "{$this->_REQUEST_SCHEME}://{$this->_HTTP_HOST}/{$this->_REQUEST_URI}" : $this->_REQUEST_URI; }
    public function getServerRootPath() { return $this->_DOCUMENT_ROOT; }
    public function getPreparedRoutesForCache() { return $this->_aRoutes; }

    /**
     * FORCE HOST
     *
     * @param string $sHost
     * @return void
     */
    public function forceHost($sHost) {
        $this->_HTTP_HOST = $sHost;
    }

    /**
     * FORCE LANGUAGE
     *
     * @param string $sLang
     * @return void
     */
    public function forceLang($sLang) {
        $this->_sLang = $sLang;
    }

    /**
     * FORCE GET PARAM EXIST
     *
     * @param array $aGET
     * @return void
     */
    public function forceExistGET($aGET) {
        foreach ($aGET as $sName) {
            if(!isset($_GET[$sName])) { $_GET[$sName] = null; }
        }
    }

    /**
     * TRANSLATE PARAM FOR URL SWITCH
     *
     * @param array $aTranslatedParam
     * @return Router
     */
    public function overloadParam($aTranslatedParam) {
        if($aTranslatedParam && is_array($aTranslatedParam)) {
            foreach($aTranslatedParam as $sLang => $aLangParams) {
                foreach($aLangParams as $sIdParam => $sTranslatedValue) {
                    $this->_aTranslateRouteParams[$sLang][$sIdParam] = urlencode($sTranslatedValue);
                }
            }
        }
        return $this;
    }

    /**
     * CLEARS TRANSLATED PARAMS
     *
     * @return Router
     */
    public function resetOverload() {
        $this->_aTranslateRouteParams = [];
        return $this;
    }

    /**
     * GIVE ACTUAL URL IN OTHER LANG
     *
     * @param string $sLang
     * @param bool $bFullUrl [optional]
     * @return string
     */
    public function switchLang($sLang, $bFullUrl = false) {

        # REQUESTED URL
        if(!$this->_sId || !isset($this->_aRoutes[$this->_sId])) { return ''; }
        if(!in_array($sLang, $this->_aRoutes[$this->_sId]['langs'])) { return ''; }
        $sUrl = $this->_aRoutes[$this->_sId]['routes'][$sLang]['rewrite'];

        # PARAM GET
        $sParamGet = empty($this->_aParamsGet) ? '' : http_build_query($this->_aParamsGet);

        # REWRITE PARAM GET
        if($this->_aRouteParamsGet) {
            $aParams = $this->_aRouteParamsGet;
            if(isset($this->_aTranslateRouteParams[$sLang])) {
                $aParams = array_replace_recursive($aParams, $this->_aTranslateRouteParams[$sLang]);
            }
            $sUrl = $this->_rewriteUrl($this->_sId, $sLang, $aParams);
        }

        # DELETE LOST PARAMS
        $sUrl = $this->_oParser->deleteLostParams($sUrl);

        # RECOMPOSED URL
        $sUrl = $sParamGet ? "$sUrl?$sParamGet" : $sUrl;
        $sUrl = trim($sUrl, '/-');
        return $bFullUrl ? "{$this->_REQUEST_SCHEME}://{$this->_HTTP_HOST}/$sUrl" : $sUrl;
    }

    /**
     * URL FABRIC
     *
     * @param string $sId
     * @param string $sLang [optional]
     * @param array $aRewriteParam [optional]
     * @param array $aGetParam [optional]
     * @param mixed $mFullUrlSheme [optional] (http, https)
     * @return string
     */
    public function url($sId, $sLang = '', $aRewriteParam = [], $aGetParam = [], $mFullUrlSheme = '') {

        # AUTO LANG
        if(!$sLang) { $sLang = $this->_sLang; }

        # REQUESTED URL
        if(!isset($this->_aRoutes[$sId]['langs']) || !in_array($sLang, $this->_aRoutes[$sId]['langs'])) { return ''; }
        $sUrl = $this->_aRoutes[$sId]['routes'][$sLang]['rewrite'];

        # PARAM GET
        $sParamGet = $aGetParam ? http_build_query($aGetParam) : '';

        # REWRITE PARAM
        if($aRewriteParam && is_array($aRewriteParam)) {
            $sUrl = $this->_rewriteUrl($sId, $sLang, $aRewriteParam);
        }

        # FULL SCHEME
        if($mFullUrlSheme) {
            if($mFullUrlSheme === true || $mFullUrlSheme === 'auto') {
                $mFullUrlSheme = $this->getHttpMode();
            }
            $mFullUrlSheme = "$mFullUrlSheme://{$this->_HTTP_HOST}/";
        }

        # DELETE LOST PARAMS
        $sUrl = $this->_oParser->deleteLostParams($sUrl);

        # RECOMPOSED URL
        $sUrl = $sParamGet ? "$sUrl?$sParamGet" : $sUrl;
        $sUrl = trim($sUrl, '/-');
        return $mFullUrlSheme . $sUrl;
    }

}
