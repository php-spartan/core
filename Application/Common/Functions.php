<?php
define('WEB_URLS',',,WWW_URL,STATIC_URL,API_URL,USER_URL,WX_URL,ATTACHMENT_URL,,');

function attachPath($path=''){
    return "../attachment/".trim($path,'/');
}
function uploadPath($path=''){
    return "../attachment/".trim($path,'/');
}
function attachUrl($path=''){
    return ATTACHMENT_URL().'/'.trim($path,'/');
}

function WWW_URL(){
    return 'http://www.'.DOMAIN;
}
function STATIC_URL(){
    return '/public/';
}
function USER_URL(){
    return '/account.html';
}
function ATTACHMENT_URL(){
    return 'http://attach.'.DOMAIN;
}
function WX_URL(){
    return 'http://wx-dev.'.DOMAIN;
}
function API_URL(){
    return 'http://api-dev.'.DOMAIN;
}
function ADMIN_URL(){
    return 'http://admin.'.DOMAIN;
}

function getOrderNumber($intUserId = 0){
    return date('ymdHis', time()).str_pad($intUserId,6,'0',STR_PAD_RIGHT).mt_rand(10000, 99999);
}
