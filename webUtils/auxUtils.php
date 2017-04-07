<?php

    function EncryptDecryptVerrnam(&$data, $dataLength, $key, $keyLength) {
        if (($dataLength == 0) || ($keyLength == 0)) {return false;}
		
        $keyOffset = 0;
        for ($dataOffset = 0; $dataOffset < $dataLength; $dataOffset++) {
            $data[$dataOffset] = $data[$dataOffset] ^ $key[$keyOffset];
            $keyOffset++;
			
            if ($keyOffset == $keyLength) {$keyOffset = 0;} 
        }
		
        return true;
    }
    
    function LoginHasRestrictedSymbols($string) {
        return !preg_match('/^[0-9a-zA-Z_-]+$/', $string);
    }
    
    function RepairBase64($base64) {
        return str_replace('_', '/', str_replace('-', '+', $base64));
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