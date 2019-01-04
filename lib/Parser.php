<?php
namespace Coercive\Utility\Router;

use Coercive\Utility\Router\Exception\ParserException;

/**
 * Parser
 *
 * @package		Coercive\Utility\Router
 * @link		@link https://github.com/Coercive/Router
 *
 * @author  	Anthony Moral <contact@coercive.fr>
 * @copyright   (c) 2016 - 2018 Anthony Moral
 * @license 	http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
class Parser {

    # PROPERTIES
    const CONTROLLER = '__';

    # REGEX
    const DEFAULT_MATCH_REGEX = '[^/]+';
    const REGEX_PARAM = '`\{([a-z_][a-z0-9_-]*)(?::([^{}]*(?:\{(?-1)\}[^{}]*)*))?\}`i';
	const REGEX_OPTION = '`\[([^\[\]]*#[0-9]+#[^\[\]]*)?\]`';
    const REGEX_OPTION_NUMBER = '`#([0-9]+)#`';
    const REGEX_LOST_OPTION = '`\[[^\]]*\]`';

    /** @var string Additional path between the route and the domain or ip */
    private $_sBasePath = '';

    /** @var array External Source Routes */
    private $_aSource = [];

    /** @var array Internal Prepared Routes */
    private $_aRoutes = [];

    /**
     * URLS CLEANER
     *
     * Destroys spaces and slashes at start and end, and all query parameters (after '?')
     *
     * @param string $url
     * @return string
     */
    public function clean(string $url): string
    {
        $url = trim($url, " \t\n\r\0\x0B/");
        return false !== ($pos = strpos($url, '?')) ? substr($url, 0, $pos) : $url;
    }

    /**
     * DELETE LOST URL PARAMS
     *
     * @param string $sUrl
     * @return string
     */
    public function deleteLostParams($sUrl) {
        return (string) preg_replace(self::REGEX_LOST_OPTION, '', preg_replace(self::REGEX_PARAM, '', $sUrl));
    }

    /**
     * SETTER BASE PATH
     * Additional path between the route and the domain or ip
     *
     * @param string $sBasePapth
     * @return Parser
     */
    public function setBasePath($sBasePapth) {
        $this->_sBasePath = $sBasePapth;
        return $this;
    }

    /**
     * SET ALREADY PREPARED ROUTES (from cache)
     *
     * @param array $aPreparedRoutes
     * @return Parser
     * @throws ParserException
     */
    public function setFromCache($aPreparedRoutes) {

        # SKIP ON ERROR
        if(!$aPreparedRoutes || !is_array($aPreparedRoutes)) { throw new ParserException('Prepared routes empty or not array'); }

        # SET
        $this->_aRoutes = $aPreparedRoutes;

        # MAINTAIN CHAINABILITY
        return $this;

    }

    /**
     * ADD ROUTES
     *
     * @param array $aRoutes
     * @return Parser
     * @throws ParserException
     */
    public function addRoutes($aRoutes) {

        # SKIP ON ERROR
        if(!$aRoutes || !is_array($aRoutes)) { throw new ParserException('Source routes empty or not array'); }

        # BIND USER ROUTES
        $this->_aSource = array_merge_recursive($this->_aRoutes, $aRoutes);

        # MAINTAIN CHAINABILITY
        return $this;

    }

    /**
     * Start process : launch of routes detection
     *
     * @return array
     * @throws ParserException
     */
    public function get() {

        # ALREADY
        if($this->_aRoutes) { return $this->_aRoutes; }

        # SKIP ON ERROR
        if(!$this->_aSource) { throw new ParserException('Parser cant start. No routes avialable.'); }

        # BASE PATH
        $sBasePath = $this->clean($this->_sBasePath);

        # PREPARE EACH ROUTE
        foreach($this->_aSource as $sId => $aRoutes) {

            # SKIP ON ERROR
            if(empty($aRoutes[self::CONTROLLER])) { throw new ParserException('Controller not found in : ' . $sId); }

            # PREPARE PROPERTIES
            $this->_aRoutes[$sId]['id'] = $sId;
            $this->_aRoutes[$sId]['controller'] = $aRoutes[self::CONTROLLER];
            $this->_aRoutes[$sId]['langs'] = [];
            $this->_aRoutes[$sId]['routes'] = [];

            # EACH LANGUAGE
            foreach($aRoutes as $sLang => $sRoute) {

                # DETECT CONTROLLER ENTRY
                if($sLang === self::CONTROLLER) { continue; }

                # CLEAN
                $sPath = $this->clean($sRoute);
                $sPath = $sPath ? ($sBasePath ? $sBasePath . '/' : '') . $sPath : $sBasePath;

                # PREPARE PROPERTIES
                $this->_aRoutes[$sId]['langs'][] = $sLang;
                $this->_aRoutes[$sId]['routes'][$sLang]['lang'] = $sLang;
                $this->_aRoutes[$sId]['routes'][$sLang]['original'] = $sPath;
                $this->_aRoutes[$sId]['routes'][$sLang] += $this->_prepareRoute($sPath);

            }
        }

        return $this->_aRoutes;

    }

    /**
     * PARSE ROUTE PARAMS
     *
     * @param string $sPath
     * @return array
     */
    private function _prepareRoute($sPath) {

        # INIT
        $aParams = [];

        # GET PARAMS (skip if not)
        if(!preg_match_all(self::REGEX_PARAM, $sPath, $aMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [
                'regex' => $sPath,
                'rewrite' => $sPath,
                'params' => $aParams
            ];
        }

        # PREPARE PARAMS & INIT CURRENT PREPARED ROOT
        foreach ($aMatches as $iKey => $aSet) {
            $aParams[$iKey] = [
                'key' => $iKey,
                'tag' => "#$iKey#",
                'subject' => $aSet[0][0],
                'name' => $aSet[1][0],
                'regex' => isset($aSet[2]) ? $aSet[2][0] : self::DEFAULT_MATCH_REGEX,
                'optional' => '',
                'optional_tag'=> ''
            ];
            $sPath = str_replace($aSet[0][0], "#$iKey#", $sPath);
        }

        # PREPARE OPTIONALS PARAMS
        if(preg_match_all(self::REGEX_OPTION, $sPath, $aMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {

            foreach ($aMatches as $aMatche) {

                preg_match_all(self::REGEX_OPTION_NUMBER, $aMatche[1][0], $aSubMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

                $sReplace = $aMatche[0][0];
                foreach ($aSubMatches as $aSubMatche) {
                    $sReplace = str_replace($aSubMatche[0][0], $aParams[$aSubMatche[1][0]]['subject'], $sReplace);
                }

                foreach ($aSubMatches as $aSubMatche) {
                    $aParams[$aSubMatche[1][0]]['optional'] = $sReplace;
                    $aParams[$aSubMatche[1][0]]['optional_tag'] = $aMatche[0][0];
                }

            }

        }

        # PREPARE REWRITED PATH
        $sRewrited = preg_replace(self::REGEX_OPTION, '(?:$1)?', $sPath);
        foreach ($aParams as $iKey => $aParam) {
            $sRewrited = str_replace("#$iKey#", "(?P<$aParam[name]>$aParam[regex])", $sRewrited);
        }

        # PREPARED ITEM
        return [
            'regex' => $sRewrited,
            'rewrite' => $sPath,
            'params' => $aParams
        ];

    }

}
