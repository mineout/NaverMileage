<?php
class NaverMileageInflowManager {
    /**
     * 네이버로부터 받는 GET 파라미터 이름
     */
    const paramName = 'Ncisy';

    /**
     * 가맹점에 구워질 쿠키 이름
     * 네이버러부터 받은 Ncisy를 urldecode->base64_encode 한상태로 저장, 쿠키이름은 변경가능 
     */
    const cookieName = 'ncisy';

    static private $instance = null;

    /**
     * 추가적립률 적용후, 가맹점 재방문시 추가적립이 허용될 도메인을 등록한다.
     * 기본적으로 레퍼러가 없을때, 가맹점 도메인과 *.naver.com 레퍼러를 가질때만 추가적립이 허용되나
     * 예외상황이 있을때는 추가를 한다. 
     * ex> 가맹점 도메인이 여러개일때 
     */
    private $aAllowedDomain = array("naver.com");
    private $cookiePath = "/";

    /**
     * 추가적립쿠키를 저장할 가맹점 도메인 
     */
    private $cookieDomain = "";

    private $rawNcisy = "";
    private $aNcisy = array();
   
    private $host;
    private $referer;
    private $aReferer;
    private $queryString;
    private $cookieNcisy;
    private $now;

    private function __construct() {
        $this->referer = getenv("HTTP_REFERER");    
        $this->aReferer = parse_url(getenv("HTTP_REFERER"));
        $this->queryString = getenv("QUERY_STRING");
        $this->host = getenv("HTTP_HOST");

        $this->cookieNcisy = $_COOKIE[self::cookieName];
        $this->now = time();

        $this->aAllowedDomain[] = $this->host;
    }

    private function __clone() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new NaverMileageInflowManager();
        }
        return self::$instance;
    }

    public function setCookieDomain($domain) {
        $this->cookieDomain = $domain;
    }

    public function setAllowedDomain($domains) {
        if (is_array($domains)) {
            foreach($domains as $domain) {
                $this->aAllowedDomain[] = $domain;
            }
        } else {
            $this->aAllowedDomain[] = $domains;
        }
    }

    /**
     * 기본 적립률 가져온다.
     */
    public function getBaseAccumRate() {
        if (is_array($this->aNcisy) && array_key_exists("ba", $this->aNcisy)) {
            return $this->aNcisy['ba'];
        } else {
            return 0;
        }
    }

    /**
     * 추가 적립률을 가져온다.
     */
    public function getAddAccumRate() {
        if (is_array($this->aNcisy) && array_key_exists("aa", $this->aNcisy)) {
            return $this->aNcisy['aa'];
        } else {
            return 0;
        }
    }
    
    /**
     * 적립/사용 팝업으로 넘겨줄 Ncisy파라미터를 얻는다.
     */
    public function getRawNcisy() {
        return urlencode($this->rawNcisy);
    }

    public function proc() {
        //Ncisy파라미터가 있으면 우선처리, Ncisy파라미터는 레퍼러가 *.naver.com일때만 유효하게 판단한다.
        if ($this->existNcisyInQueryString()) {
            $this->processParam(); 
            //파라미터내 만료시간이 지났으면 무효처리 
            if ($this->isExpired($this->aNcisy['e'])) {
                $this->clean();
            } else {
                $this->issueCookie();
            }
        } else {
            //Ncisy정보를 쿠키에 저장한 상태 
            if ($this->existNcisyCookie()) {
                //레퍼러가 없거나, *.naver.com 이거나 예외처한 도메인일경우 
                if ($this->isRefererAllowedDomain()) {
                    $this->processCookie();

                    //쿠키에 저장된 Ncisy의 만료시간이 지났으면 삭제처리 
                    if ($this->isExpired($this->aNcisy['e'])) {
                        $this->clean();
                        $this->cleanCookie();
                    } else {
                        $this->processCookie(); 
                    }
                } else { 
                    //타 사이트 레퍼러로 접근시는 쿠키는 삭제한다. 
                    $this->clean();
                    $this->cleanCookie();
                }
            } else {
                //Do nothing
                $this->clean();
            }
        }
    }

    private function processCookie() {
        $aNcisy = array();
        $ncisy = $this->getNcisyInCookie();
        if (strlen($ncisy) > 0) {
            $aNcisy = $this->parseNcisy($ncisy);
            if ($this->isValidNcisy($aNcisy) === true) {
                $this->rawNcisy = $ncisy;
                $this->aNcisy = $aNcisy;
            }
        }
    }

    private function processParam() {
        $aNcisy = array();
        $ncisy = $this->getNcisyInQueryString();
        $ncisy = urldecode($ncisy);
        if (strlen($ncisy) > 0) {
            $aNcisy = $this->parseNcisy($ncisy);
            if ($this->isValidNcisy($aNcisy) === true) {
                $this->rawNcisy = $ncisy;
                $this->aNcisy = $aNcisy;
            }
        }
    }
    
    private function issueCookie() {
        $domain = "";
        if ($this->now <= $this->aNcisy['e']) {
            if (!$this->cookieDomain) {
                $domain = $this->host;
            } else {
                $domain = $this->cookieDomain;
            }
            setCookie(self::cookieName, base64_encode($this->rawNcisy), 0, $this->cookiePath, $domain);
        }
    }

    private function clean() {
        $this->rawNcisy = "";
        $this->aNcisy = array();
    }

    private function cleanCookie() {
        setCookie(self::cookieName, "", 0, $this->cookiePath, $this->cookieDomain);
    }

    private function isRefererFromNaver() {
        if (@preg_match("/naver\.com/", $this->aReferer['host'])) {
            return true;
        } else {
            return false;
        }
    }

    private function isRefererAllowedDomain() {
        if (!$this->referer) {
            return true;
        } else {
            for($i = 0;$i < count($this->aAllowedDomain);$i++) {
                if (@preg_match("/".preg_quote($this->aAllowedDomain[$i])."/", $this->aReferer['host'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getNcisyInCookie() {
        if ($this->existNcisyCookie()) {
            $cookieNcisy = urldecode(base64_decode($this->cookieNcisy));
            return $cookieNcisy;
        }
        return "";
    }

    private function getNcisyInQueryString() {
        if ($this->existNcisyInQueryString() === true) {
            $params = explode("&", $this->queryString);
            for ($i = 0; $i < count($params); $i++) {
                list($name, $value) = explode("=", $params[$i]);                
                if ($name == self::paramName) {
                    return $value;
                }
            }
        }
        return "";
    }

    private function existNcisyCookie() {
        if ($this->cookieNcisy && strlen($this->cookieNcisy)) {
            return true;
        } else {
            return false;
        }
    }

    private function existNcisyInQueryString() {
        if ($this->isRefererFromNaver() && $this->queryString  && preg_match("/Ncisy=/", $this->queryString)) {
            return true;
        } else {
            return false;
        }
    }
    
    private function parseNcisy($str) {
        $str = urldecode($str);
        $ncisy = explode("|", $str);
        $aNcisy = array();

        for($i=0;$i<count($ncisy);$i++) {
            list($name, $value) = explode('=', $ncisy[$i]);  
            $aNcisy[$name] = $value;
        }

        return $aNcisy;
    }

    private function isValidNcisy($aNcisy) {
        if (is_array($aNcisy) && count($aNcisy) > 0 
            && array_key_exists("e" , $aNcisy)
            && array_key_exists("ba", $aNcisy)
            && array_key_exists("aa", $aNcisy)) {
            return true;
        } else {
            return false;
        }
    }

    private function isExpired($expiration) {
        if ($this->now > $expiration) {
           return true; 
        } else {
            return false;
        }
    }
}
?>
