<?php
$soap = new SoapClient('https://tckimlik.nvi.gov.tr/service/kpspublic.asmx?wsdl');
$id_number = '11111111110';
$name = 'ad';
$surname = 'soyad';
$birth_year = '1980';
$result = $soap->TCKimlikNoDogrula($id_number, $name, $surname, $birth_year)->TCKimlikNoDogrulaResult;
$json = json_encode(['result' => $result]);
echo $json;
