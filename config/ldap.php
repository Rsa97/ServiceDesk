<?php
$defaultDomain = 'sod.local';
$ldapServers = array('10.149.0.209', '10.149.0.211');
$userBase = 'CN=Users,dc=sod,dc=local';
$attrMapping = array('firstName' => 'givenName',
		     'lastName' => 'sn',
		     'middleName' => 'middleName',
		     'phone'      => 'telephoneNumber',
		     'mail'       => 'mail');
$userAttr = 'sAMAccountName';
$adminFilter = 'memberOf:1.2.840.113556.1.4.1941:=CN=SD_Admins,OU=Permissions,DC=sod,DC=local';
$operFilter = 'memberOf:1.2.840.113556.1.4.1941:=CN=SD_Operators,OU=Permissions,DC=sod,DC=local';
$engeneerFilter = 'memberOf:1.2.840.113556.1.4.1941:=CN=SD_Engeneers,OU=Permissions,DC=sod,DC=local';
$userFilter = 'memberOf:1.2.840.113556.1.4.1941:=CN=Пользователи домена,OU=Groups,DC=sod,DC=local';
?>