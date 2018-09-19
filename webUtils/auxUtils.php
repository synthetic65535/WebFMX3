<?php
    
    function HexDecode($data) {
        if ($data == '') {return '';}
        $dataLength = strlen($data);
        $result = '';
        for ($i=0; $i < $dataLength - 1; $i+=2){
            $result .= chr(hexdec($data[$i].$data[$i+1]));
        }
        return $result;
    }
    
    function IntToBinary($i) {
        return chr($i >> 24).chr($i >> 16).chr($i >> 8).chr($i);
    }
    
    function BinaryToInt($b) {
        return (ord($b[0]) << 24) + (ord($b[1]) << 16) + (ord($b[2]) << 8) + ord($b[3]);
    }
    
    // Структура зашифрованной информации:
    //   (salt)(iv)[(length)(data)(filler)(md5)]
    //   salt, 16 байт - соль для генерации ключа шифрования по алгоритму PBKDF2.
    //   iv, 16 байт - начальный вектор для алгоритма шифрования Rijndael.
    //   length, 4 байта - размер полезных данных.
    //   filler, от 0 байт - случайные байты, которые дополняют байты length+data до длины, кратной 16.
    //   md5, 16 байт - хеш от data.
    //   Всё, что в квадратных скобках - зашифровано.
    
    function EncryptRijndael(&$data, $password, $expanded_data_length = 204) {
        $salt = openssl_random_pseudo_bytes(16);
        $iv = openssl_random_pseudo_bytes(16);
        $data_length = strlen($data);
        $checksum = md5($data, true);
        $prepared_data = IntToBinary($data_length).$data;
        if ($expanded_data_length > $data_length)
            $prepared_data .= openssl_random_pseudo_bytes($expanded_data_length - $data_length);
        $prepared_data_length = strlen($prepared_data);
        while (($prepared_data_length % 16) != 0) {
            $prepared_data = $prepared_data.chr(rand(0x00, 0xff));
            $prepared_data_length++;
        }
        $key = openssl_pbkdf2($password, $salt, 32, 1000, 'sha1');
        $td = mcrypt_module_open('rijndael-128', '', 'cbc', '');
        if (mcrypt_generic_init($td, $key, $iv) === 0) 
        {
            $encrypted_data = mcrypt_generic($td, $prepared_data.$checksum);
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        } else {
            return false; // Error: Initializatioin failed
        }
        $data = $salt.$iv.$encrypted_data;
        return true;
    }
    
    function DecryptRijndael(&$data, $password) {
        if (strlen($data) < 64) {
            return false; // Error: Not enough data to decrypt
        }
        $salt = substr($data, 0, 16);
        $iv = substr($data, 16, 16);
        $extended_data_length = strlen($data) - 32;
        $encrypted_data = substr($data, 32, $extended_data_length);
        $key = openssl_pbkdf2($password, $salt, 32, 1000, 'sha1');
        $td = mcrypt_module_open('rijndael-128', '', 'cbc', '');
        if (mcrypt_generic_init($td, $key, $iv) === 0) 
        {
            $decrypted_data = mdecrypt_generic($td, $encrypted_data);
            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);
        } else {
            return false; // Error: Initializatioin failed
        }
        $data_length_binary = substr($decrypted_data, 0, 4);
        $data_length = BinaryToInt($data_length_binary);
        if ($data_length > $extended_data_length - 20) {
            return false; // Error: DataLength is too big
        }
        $result = substr($decrypted_data, 4, $data_length);
        $checksum = substr($decrypted_data, strlen($decrypted_data) - 16, 16);
        if ($checksum != md5($result, true)) {
            return false; // Error: Data is not valid
        }
        $data = $result;
        return true;
    }
    
    function LoginHasRestrictedSymbols($string) {
        return !preg_match('/^[0-9a-zA-Z_-]+$/', $string);
    }
    
    // Генерация UUID по версии Spigot:
    function GenerateUUID ($login) {
        $val = md5('OfflinePlayer:'.$login, true);
        $byte = array_values(unpack('C16', $val));
        $tLo = ($byte[0] << 24) | ($byte[1] << 16) | ($byte[2] << 8) | $byte[3];
        $tMi = ($byte[4] << 8) | $byte[5];
        $tHi = ($byte[6] << 8) | $byte[7];
        $csLo = $byte[9];
        $csHi = $byte[8] & 0x3f | (1 << 7);
        
        if (pack('L', 0x6162797A) == pack('N', 0x6162797A)) {
            $tLo = (($tLo & 0x000000ff) << 24) | (($tLo & 0x0000ff00) << 8) | (($tLo & 0x00ff0000) >> 8) | (($tLo & 0xff000000) >> 24);
            $tMi = (($tMi & 0x00ff) << 8) | (($tMi & 0xff00) >> 8);
            $tHi = (($tHi & 0x00ff) << 8) | (($tHi & 0xff00) >> 8);
        }
        $tHi &= 0x0fff;
        $tHi |= (3 << 12);
        
        $uuid = sprintf('%08x%04x%04x%02x%02x%02x%02x%02x%02x%02x%02x', $tLo, $tMi, $tHi, $csHi, $csLo, $byte[10], $byte[11], $byte[12], $byte[13], $byte[14], $byte[15]);
        return $uuid;
    }
    
    function GenerateProfileInfo($uuid, $username, $workingFolder, $skinsFolder, $cloaksFolder, $defSkinName, $defCloakName, $cloaksPostfix) {
        
        $timestamp = time() * 1000;
        
        $skinRelativePath = $skinsFolder.'/'.$username.'.png';
        $cloakRelativePath = $cloaksFolder.'/'.$username.$cloaksPostfix.'.png';
        
        $skinUrl  = $workingFolder.'/'.$skinRelativePath;
        $cloakUrl = $workingFolder.'/'.$cloakRelativePath;
        
        $skinExists  = file_exists($skinRelativePath);
        $cloakExists = file_exists($cloakRelativePath);
        
        if (!$skinExists) {
            $skinRelativePath = $skinsFolder.'/'.$defSkinName;
            $skinExists = file_exists($skinRelativePath);
            if ($skinExists) {
                $skinUrl = $workingFolder.'/'.$skinRelativePath;
            }
        }
        
        if (!$cloakExists) {
            $cloakRelativePath = $cloaksFolder.'/'.$defCloakName;
            $cloakExists = file_exists($cloakRelativePath);
            if ($cloakExists) {
                $cloakUrl = $workingFolder.'/'.$cloakRelativePath;
            }
        }
        
        if ((!$skinExists) && (!$cloakExists)) {return null;}
        
        $basicProfileInfo = '"timestamp":"'.$timestamp.'","profileId":"'.$uuid.'","profileName":"'.$username.'"';
        $skinBlock  = '"SKIN":{"url":"'.$skinUrl.'"}';
        $cloakBlock = '"CAPE":{"url":"'.$cloakUrl.'"}';
        
        if ($skinExists || $cloakExists) {
            // Если существует только или скин, или плащ:
            if (!($skinExists && $cloakExists)) {
                $texturesInfo = $skinExists ? $skinBlock : $cloakBlock;
            } else {
                // Если существуют и скин, и плащ:
                $texturesInfo = $skinBlock.','.$cloakBlock;
            }
            $texturesBlock = '"textures":{'.$texturesInfo.'}';
            $profileInfo = '{'.$basicProfileInfo.','.$texturesBlock.'}';
            
        } else {
            // Если нет ни скина, ни плаща:
            $texturesBlock = '"textures":{'.$texturesInfo.'}';
            $profileInfo = '{'.$basicProfileInfo.'}';
        }
        return base64_encode($profileInfo);
    }
    
?>
