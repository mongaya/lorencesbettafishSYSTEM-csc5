<?php
/* Shipping rates are server-owned; customers cannot supply a fee. */
function regional_shipping_fee($address) {
    $address = mb_strtolower(trim((string)$address), 'UTF-8');
    $address = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $address);
    $address = ' '.preg_replace('/\s+/u',' ',$address).' ';
    $luzon = ["Abra","Albay","Apayao","Aurora","Bataan","Batanes","Batangas","Benguet","Bulacan","Cagayan","Camarines Norte","Camarines Sur","Catanduanes","Cavite","Ifugao","Ilocos Norte","Ilocos Sur","Isabela","Kalinga","La Union","Laguna","Marinduque","Masbate","Mountain Province","Nueva Ecija","Nueva Vizcaya","Occidental Mindoro","Oriental Mindoro","Palawan","Pampanga","Pangasinan","Quezon","Quirino","Rizal","Romblon","Sorsogon","Tarlac","Zambales","Metro Manila","NCR","Manila","Quezon City","Caloocan","Makati","Taguig","Pasig","Pasay","Paranaque","Parañaque","Muntinlupa","Marikina","Mandaluyong","Valenzuela","Malabon","Navotas","San Juan","Las Pinas","Las Piñas","Pateros"];
    $vismin = ["Aklan","Antique","Biliran","Bohol","Capiz","Cebu","Eastern Samar","Guimaras","Iloilo","Leyte","Negros Occidental","Negros Oriental","Northern Samar","Samar","Southern Leyte","Siquijor","Western Samar","Agusan del Norte","Agusan del Sur","Basilan","Bukidnon","Camiguin","Compostela Valley","Davao de Oro","Davao del Norte","Davao del Sur","Davao Occidental","Davao Oriental","Dinagat Islands","Lanao del Norte","Lanao del Sur","Maguindanao","Maguindanao del Norte","Maguindanao del Sur","Misamis Occidental","Misamis Oriental","North Cotabato","Cotabato","Sarangani","South Cotabato","Sultan Kudarat","Sulu","Surigao del Norte","Surigao del Sur","Tawi-Tawi","Zamboanga del Norte","Zamboanga del Sur","Zamboanga Sibugay","Cebu City","Davao City","Iloilo City","Bacolod","Cagayan de Oro","General Santos","Zamboanga City","Butuan","Tacloban","Tagbilaran","Dumaguete","Puerto Princesa"];
    $matches = [];
    foreach (['Luzon'=>$luzon,'Visayas/Mindanao'=>$vismin] as $region=>$places) {
        foreach ($places as $place) {
            $term = ' '.preg_replace('/\s+/u',' ',mb_strtolower($place,'UTF-8')).' ';
            if ($region==='Luzon' && $place==='Cagayan' && strpos($address,' cagayan de oro ')!==false) continue;
            if (strpos($address,$term)!==false) $matches[$region]=true;
        }
    }
    if (count($matches)!==1) return null;
    return isset($matches['Luzon']) ? 80.0 : 170.0;
}
