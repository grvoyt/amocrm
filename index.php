<?php
require_once 'amocrm.php';
require_once 'config.php';


$amo = new Amocrm($email,$api_key,$domain,true);

$amocrm_map = [
    'phone' => '285787', //поля в амоCrm
    'email' => '285789',
];
$amo->setMap($amocrm_map); // установка кастомных полей
$amo->getMap();
$amo->setStatus('15663874'); // eads_status id Воронка для лидов
$amo->setTags('mail.rf'); // Установка тегов к заявке

$amo->auth(); // Аунтификация в амосрм

$lead = array(
	'lead_name' => '#230133 Заявка на Директ', // заголовок лида
);
$amo->leadSet($lead);
print_r( $amo->getLeadId()); // отправка лида
die;

$cont = array(
	'phone' => '89261234567',
	'email' => 'info@kingdirect.ru',
	'name' => 'Семен',
);
$amo->contactSet($cont); // создание контакта

// Цепляем заметку к лиду через универсальную добавлялку
$note=array(
    'element_type'=>2, // 2 - Сделка
    'note_type'=>4, // 4 - Обычная заметка
    'text'=> 'Заметка лида' // тут текст лида
);
$amo->amocrm_add_one($note, 'notes'); // создание заметки

// Создание задачи

$task = array(
    'element_type'=>2, // 2 - Сделка
    'task_type'=>1, // #Звонок
    'text'=>'Перезвонить',
    'responsible_user_id' => '1587373', //Александра ИД
    'complete_till'=>86400000,
);

$amo->amocrm_add_one($note, 'tasks'); //создание задания