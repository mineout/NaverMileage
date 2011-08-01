<?php
require_once "./lib/NaverMileageInflowManager.class.php";

$inflowManager = NaverMileageInflowManager::getInstance();

//쿠키 도메인 설정(미설정시 해당 host)
$inflowManager->setCookieDomain(".mymall.com");

//추가적립이 적용될 예외 도메인 추가 
$inflowManager->setAllowedDomain(array("mymall2.com"));

$inflowManager->proc();

//가맹점 기본적립률
echo $inflowManager->getBaseAccumRate();

//네이버 추가적립률 
echo $inflowManager->getAddAccumRate();

//네이버마일리지 적립/사용 팝업으로 넘겨줄 Ncisy값(urlencoding된 상태)
echo $inflowManager->getRawNcisy();
?>
