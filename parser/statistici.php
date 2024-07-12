<?php

/**
 * Gaseste cea mai mica nota din lista, astfel incat $procent% din note sunt mai mari sau egale cu ea
 * Face media aritmetica a primilor $procent% note din lista
 * Intoarce un vector cu doua chei, ex. pentru $procent = 10 va intoarce ["n10" => ..., "m10" => ...] - n10 este ultima nota a primilor 10%, m10 e media primilor 10%
 * Functia asta se aseamana cu percentila, dar nu e
 **/
function calcul_top(array $note, int $procent){
    $index = round(count($note) * $procent / 100);
    if($index > 0){
        $index--;
    }
    return [
        "n" . $procent => $note[$index],
        "m" . $procent => round(array_sum(array_splice($note, 0, $index + 1)) / ($index + 1), 2)
    ];
}

$anul = 2024;
$time = microtime(true);

$judete = ["AB" => "ALBA", "AG" => "ARGEȘ", "AR" => "ARAD", "B" => "BUCUREȘTI", "BC" => "BACĂU", "BH" => "BIHOR", "BN" => "BISTRIȚA-NĂSĂUD", "BR" => "BRĂILA", "BT" => "BOTOȘANI", "BV" => "BRAȘOV", "BZ" => "BUZĂU", "CJ" => "CLUJ", "CL" => "CĂLĂRAȘI", "CS" => "CARAȘ-SEVERIN", "CT" => "CONSTANȚA", "CV" => "COVASNA", "DB" => "DÂMBOVIȚA", "DJ" => "DOLJ", "GJ" => "GORJ", "GL" => "GALAȚI", "GR" => "GIURGIU", "HD" => "HUNEDOARA", "HR" => "HARGHITA", "IF" => "ILFOV", "IL" => "IALOMIȚA", "IS" => "IAȘI", "MH" => "MEHEDINȚI", "MM" => "MARAMUREȘ", "MS" => "MUREȘ", "NT" => "NEAMȚ", "OT" => "OLT", "PH" => "PRAHOVA", "SB" => "SIBIU", "SJ" => "SĂLAJ", "SM" => "SATU MARE", "SV" => "SUCEAVA", "TL" => "TULCEA", "TM" => "TIMIȘ", "TR" => "TELEORMAN", "VL" => "VÂLCEA", "VN" => "VRANCEA", "VS" => "VASLUI"];

try{
    $db = new PDO("sqlite:" . $anul . "/db.sqlite");
} catch(PDOException $e) {
    die($e->getMessage());
}

$empty = [
    "nume" => "", // România, Olt sau Colegiul National Tudor Vianu
    "nivel" => 0, // 0 = national, 1 = judet, 2 = localitate, 3 = scoala
    "mediu" => "GLOBAL", // , -- global, rural sau urban (valabil doar daca nivel e 0 sau 1)
    "prez" => 0, // numarul de note valide
    "abs" => 0, // numarul de absenti
    "med" => 0, // media aritmetica
    "n10" => 0, // ultima nota a primilor 10%
    "m10" => 0, // media primilor 10%
    "n25" => 0, // ultima nota a primului sfert
    "m25" => 0, // media primului sfert
    "n50" => 0, // ultima nota a primei jumatati (aproape mediana)
    "m50" => 0, // media primei jumatati
    "p100" => 0, // numar de note de 10
    "p95" => 0, // numar de note intre 9.5 si 9.99
    "p90" => 0, // numar de note intre 9.0 si 9.49
    "p85" => 0, // numar de note intre 8.5 si 8.99
    "p80" => 0, // numar de note intre 8.0 si 8.49
    "p75" => 0, // numar de note intre 7.5 si 7.99
    "p70" => 0, // numar de note intre 7.0 si 7.49
    "p65" => 0, // numar de note intre 6.5 si 6.99
    "p60" => 0, // numar de note intre 6.0 si 6.49
    "p55" => 0, // numar de note intre 5.5 si 5.99
    "p50" => 0, // numar de note intre 5.0 si 5.49
    "p45" => 0, // numar de note intre 4.5 si 4.99
    "p40" => 0, // numar de note intre 4.0 si 4.49
    "p35" => 0, // numar de note intre 3.5 si 3.99
    "p30" => 0, // numar de note intre 3.0 si 3.49
    "p25" => 0, // numar de note intre 2.5 si 2.99
    "p20" => 0, // numar de note intre 2.0 si 2.49
    "p15" => 0, // numar de note intre 1.5 si 1.99
    "p10" => 0, // numar de note intre 1.0 si 1.49
    "medii" => [], // mediile de la evaluare
];


$stats = [
    "RO" => ["nume" => "ROMÂNIA"] + $empty,
    "RO:URBAN" => ["nume" => "ROMÂNIA (URBAN)", "mediu" => "URBAN"] + $empty,
    "RO:RURAL" => ["nume" => "ROMÂNIA (RURAL)", "mediu" => "RURAL"] + $empty,
];

foreach($db->query("SELECT note.scoala, note.judet, scoli.localitate, scoli.mediu, scoli.nume, note.medie, scoli.prezenti, scoli.absenti FROM note INNER JOIN scoli ON note.scoala = scoli.cod", PDO::FETCH_OBJ) as $nota){
    if(!isset($stats[$nota->judet])){
        $stats[$nota->judet] = ["nume" => $judete[$nota->judet], "nivel" => 1] + $empty;
    }

    if(!isset($stats[$nota->judet . ":" . $nota->mediu])){
        $stats[$nota->judet . ":" . $nota->mediu] = ["nume" => $judete[$nota->judet] . " (" . $nota->mediu . ")", "nivel" => 1, "mediu" => $nota->mediu] + $empty;
    }

    if(!isset($stats[$nota->judet . ":" . $nota->localitate])){
        $stats[$nota->judet . ":" . $nota->localitate] = ["nume" => $nota->localitate . ($nota->judet == "B" ? "" : ", " . $nota->judet), "nivel" => 2, "mediu" => $nota->mediu] + $empty;
    }

    if(!isset($stats[$nota->scoala])){
        $stats[$nota->scoala] = ["nume" => $nota->nume, "nivel" => 3, "mediu" => $nota->mediu, "prez" => $nota->prezenti, "abs" => $nota->absenti] + $empty;
        $stats[$nota->judet . ":" . $nota->localitate]["prez"] += $nota->prezenti;
        $stats[$nota->judet . ":" . $nota->localitate]["abs"] += $nota->absenti;
        $stats[$nota->judet . ":" . $nota->mediu]["prez"] += $nota->prezenti;
        $stats[$nota->judet . ":" . $nota->mediu]["abs"] += $nota->absenti;
        $stats[$nota->judet]["prez"] += $nota->prezenti;
        $stats[$nota->judet]["abs"] += $nota->absenti;
        $stats["RO:" . $nota->mediu]["prez"] += $nota->prezenti;
        $stats["RO:" . $nota->mediu]["abs"] += $nota->absenti;
        $stats["RO"]["prez"] += $nota->prezenti;
        $stats["RO"]["abs"] += $nota->absenti;
    }

    // nu salvez notele absentilor
    if($nota->medie < 0){
        continue;
    }

    $stats["RO"]["medii"][] = $nota->medie;
    $stats["RO:" . $nota->mediu]["medii"][] = $nota->medie;
    $stats[$nota->judet]["medii"][] = $nota->medie;
    $stats[$nota->judet . ":" . $nota->mediu]["medii"][] = $nota->medie;
    $stats[$nota->judet . ":" . $nota->localitate]["medii"][] = $nota->medie;
    $stats[$nota->scoala]["medii"][] = $nota->medie;

}

// Calculez statisticile pentru fiecare loc
foreach($stats as &$loc){
    if($loc["prez"] == 0){
        continue;
    }

    rsort($loc["medii"]); // sortare descrescatoare

    $loc["med"] = round(array_sum($loc["medii"]) / count($loc["medii"]), 2);

    $loc = array_merge(
        $loc,
        calcul_top($loc["medii"], 10),
        calcul_top($loc["medii"], 25),
        calcul_top($loc["medii"], 50)
    );

    foreach($loc["medii"] as $med){
        $loc["p" . (floor($med * 2) * 5)]++;
    }

    unset($loc["medii"]);
}



echo "@Line " . __LINE__ . ": +" . round(1000 * (microtime(true) - $time)) . " ms\n"; $time = microtime(true);

file_put_contents($anul . "/raport.json", json_encode($stats));

echo "Dimensiune: " . count($stats) . " inregistrari\n";
echo "Memorie: " . round(memory_get_usage() / 1048576, 2) . " Mb\n";

echo "@Line " . __LINE__ . ": +" . round(1000 * (microtime(true) - $time)) . " ms\n"; $time = microtime(true);
