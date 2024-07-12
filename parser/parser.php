<?php

// Script pentru agregat datele din baza de date si obtinut JSON-urile care tine tot ce e nevoie

$anul = 2023;
$start = microtime(true);

try{
    $db = new PDO("sqlite:" . $anul . "/db.sqlite");
} catch(PDOException $e) {
    die($e->getMessage());
}

$db->exec("DROP TABLE IF EXISTS raport");
$db->exec("CREATE TABLE raport (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parinte INTEGER REFERENCES raport(id),
    nume TEXT NOT NULL DEFAULT '', -- România, Olt sau Colegiul National Tudor Vianu
    nivel INTEGER NOT NULL DEFAULT 0, -- 0 = national, 1 = judet, 2 = localitate, 3 = scoala
    mediu TEXT NOT NULL DEFAULT 'GLOBAL', -- global, rural sau urban (valabil doar daca nivel e 0 sau 1)
    prez INTEGER NOT NULL DEFAULT 0, -- numarul de note valide
    abs INTEGER NOT NULL DEFAULT 0, -- numarul de absenti
    med REAL NOT NULL DEFAULT 0, -- media aritmetica
    n10 REAL NOT NULL DEFAULT 0, -- ultima nota a primilor 10%
    m10 REAL NOT NULL DEFAULT 0, -- media primilor 10%
    n25 REAL NOT NULL DEFAULT 0, -- ultima nota a primului sfert
    m25 REAL NOT NULL DEFAULT 0, -- media primului sfert
    n50 REAL NOT NULL DEFAULT 0, -- ultima nota a primei jumatati (mediana)
    m50 REAL NOT NULL DEFAULT 0, -- media primei jumatati
    p100 INTEGER NOT NULL DEFAULT 0, -- numar de note de 10
    p95 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 9.5 si 9.99
    p90 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 9.0 si 9.49
    p85 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 8.5 si 8.99
    p80 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 8.0 si 8.49
    p75 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 7.5 si 7.99
    p70 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 7.0 si 7.49
    p65 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 6.5 si 6.99
    p60 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 6.0 si 6.49
    p55 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 5.5 si 5.99
    p50 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 5.0 si 5.49
    p45 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 4.5 si 4.99
    p40 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 4.0 si 4.49
    p35 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 3.5 si 3.99
    p30 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 3.0 si 3.49
    p25 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 2.5 si 2.99
    p20 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 2.0 si 2.49
    p15 INTEGER NOT NULL DEFAULT 0, -- numar de note intre 1.5 si 1.99
    p10 INTEGER NOT NULL DEFAULT 0  -- numar de note intre 1.0 si 1.49
)");

$db->exec("CREATE INDEX IF NOT EXISTS idx_note_judet ON note(judet)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_note_medie ON note(medie)");

/* TODO: scoate comentariu asta inainte de commit
echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

try {
    $db->exec("ALTER TABLE note DROP mediu");
} catch(PDOException $e){
    // coloana e posibil sa nu existe...
}
$db->exec("ALTER TABLE note ADD mediu TEXT NOT NULL DEFAULT 'URBAN'");
$db->exec("UPDATE note SET mediu = 'RURAL' WHERE scoala IN (SELECT cod FROM scoli WHERE mediu = 'RURAL')");
*/

echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . "\n";

/**
 * Calculele nationale
 **/

$ro = [
    "GLOBAL" => ["nume" =>"ROMÂNIA", "nivel" => 0, "mediu" => "GLOBAL"],
    "URBAN" => ["nume" =>"ROMÂNIA (URBAN)", "nivel" => 0, "mediu" => "URBAN"],
    "RURAL" => ["nume" =>"ROMÂNIA (RURAL)", "nivel" => 0, "mediu" => "RURAL"]
];

// mediile nationale
$ro["GLOBAL"] += $db->query("SELECT ROUND(AVG(medie), 2) AS med, COUNT(*) AS prez FROM note WHERE medie > 0")->fetch(PDO::FETCH_ASSOC);
foreach($db->query("SELECT mediu, ROUND(AVG(medie), 2) AS med, COUNT(*) AS prez FROM note WHERE medie > 0 GROUP BY mediu", PDO::FETCH_ASSOC) as $row){
    $ro[$row["mediu"]] += $row;
}
echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

// absenteismul national
foreach($db->query("SELECT mediu, SUM(absenti) AS abs FROM scoli GROUP BY mediu", PDO::FETCH_OBJ) as $row){
    $ro[$row->mediu]["abs"] = $row->abs;
}
$ro["GLOBAL"]["prez"] = $ro["URBAN"]["prez"] + $ro["RURAL"]["prez"];
$ro["GLOBAL"]["abs"] = $ro["URBAN"]["abs"] + $ro["RURAL"]["abs"];

echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

// top 10%, 25% si 50%
foreach([10, 25, 50] as $procent){
    $ro["GLOBAL"] += $db->query("SELECT ROUND(AVG(medie), 2) AS m" . $procent . ", MIN(medie) AS n" . $procent . " FROM (SELECT medie FROM note ORDER BY medie DESC LIMIT " . round($ro["GLOBAL"]["prez"] * $procent / 100) . ")")->fetch(PDO::FETCH_ASSOC);
    $ro["URBAN"] += $db->query("SELECT ROUND(AVG(medie), 2) AS m" . $procent . ", MIN(medie) AS n" . $procent . " FROM (SELECT medie FROM note WHERE mediu = 'URBAN' ORDER BY medie DESC LIMIT " . round($ro["URBAN"]["prez"] * $procent / 100) . ")")->fetch(PDO::FETCH_ASSOC);
    $ro["RURAL"] += $db->query("SELECT ROUND(AVG(medie), 2) AS m" . $procent . ", MIN(medie) AS n" . $procent . " FROM (SELECT medie FROM note WHERE mediu = 'RURAL' ORDER BY medie DESC LIMIT " . round($ro["RURAL"]["prez"] * $procent / 100) . ")")->fetch(PDO::FETCH_ASSOC);
}

echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

// numarul de grupuri de note pe fiecare mediu
foreach($db->query("SELECT mediu, FLOOR(medie * 10 / 5) * 5 AS med, COUNT(*) AS num FROM note WHERE medie > 0 GROUP BY mediu, med", PDO::FETCH_OBJ) as $row) {
    $ro[$row->mediu]["p" . $row->med] = $row->num;
    if(!isset($ro["GLOBAL"]["p" . $row->med])) {
        $ro["GLOBAL"]["p" . $row->med] = $row->num;
    } else {
        $ro["GLOBAL"]["p" . $row->med] += $row->num;
    }
}

$insert = $db->prepare("INSERT INTO raport (nume, nivel, mediu, prez, abs, med, n10, m10, n25, m25, n50, m50, p100, p95, p90, p85, p80, p75, p70, p65, p60, p55, p50, p45, p40, p35, p30, p25, p20, p15, p10) VALUES (:nume, :nivel, :mediu, :prez, :abs, :med, :n10, :m10, :n25, :m25, :n50, :m50, :p100, :p95, :p90, :p85, :p80, :p75, :p70, :p65, :p60, :p55, :p50, :p45, :p40, :p35, :p30, :p25, :p20, :p15, :p10)");
foreach($ro as $mediu => $row){
    $insert->execute($row);
}

echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

echo "Am terminat calculele nationale\n";

/**
 * Calculele judetene
 **/

$judete = ["AB" => "ALBA", "AG" => "ARGEȘ", "AR" => "ARAD", "B" => "BUCUREȘTI", "BC" => "BACĂU", "BH" => "BIHOR", "BN" => "BISTRIȚA-NĂSĂUD", "BR" => "BRĂILA", "BT" => "BOTOȘANI", "BV" => "BRAȘOV", "BZ" => "BUZĂU", "CJ" => "CLUJ", "CL" => "CĂLĂRAȘI", "CS" => "CARAȘ-SEVERIN", "CT" => "CONSTANȚA", "CV" => "COVASNA", "DB" => "DÂMBOVIȚA", "DJ" => "DOLJ", "GJ" => "GORJ", "GL" => "GALAȚI", "GR" => "GIURGIU", "HD" => "HUNEDOARA", "HR" => "HARGHITA", "IF" => "ILFOV", "IL" => "IALOMIȚA", "IS" => "IAȘI", "MH" => "MEHEDINȚI", "MM" => "MARAMUREȘ", "MS" => "MUREȘ", "NT" => "NEAMȚ", "OT" => "OLT", "PH" => "PRAHOVA", "SB" => "SIBIU", "SJ" => "SĂLAJ", "SM" => "SATU MARE", "SV" => "SUCEAVA", "TL" => "TULCEA", "TM" => "TIMIȘ", "TR" => "TELEORMAN", "VL" => "VÂLCEA", "VN" => "VRANCEA", "VS" => "VASLUI"];
$jud = [];


// mediile judetene
foreach($db->query("SELECT judet, ROUND(AVG(medie), 2) AS med, COUNT(*) AS prez FROM note WHERE medie > 0 GROUP BY judet", PDO::FETCH_OBJ) as $row) {
    $jud[$row->judet]["GLOBAL"] = [
        "nume" => $judete[$row->judet],
        "nivel" => 1,
        "mediu" => "GLOBAL",
        "med" => $row->med,
        "prez" => $row->prez
    ];
}
foreach($db->query("SELECT judet, mediu, ROUND(AVG(medie), 2) AS med, COUNT(*) AS prez FROM note WHERE medie > 0 GROUP BY judet, mediu", PDO::FETCH_OBJ) as $row){
    $jud[$row->judet][$row->mediu] = [
        "nume" => $judete[$row->judet] . " (" . $row->mediu . ")",
        "nivel" => 1,
        "mediu" => $row->mediu,
        "med" => $row->med,
        "prez" => $row->prez
    ];
}
echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

// absenteismul judetean
foreach($db->query("SELECT judet, mediu, SUM(absenti) AS abs FROM scoli GROUP BY judet, mediu", PDO::FETCH_OBJ) as $row){
    $jud[$row->judet][$row->mediu]["abs"] = $row->abs;
    if(!isset($jud[$row->judet]["GLOBAL"]["abs"])){
        $jud[$row->judet]["GLOBAL"]["abs"] = $row->abs;
    } else {
        $jud[$row->judet]["GLOBAL"]["abs"] += $row->abs;
    }
}
echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

// top 10%, 25% si 50%
foreach([10, 25, 50] as $procent){
    foreach($jud as $j => $judet){
        $jud[$j]["GLOBAL"] += $db->query("SELECT ROUND(AVG(medie), 2) AS m" . $procent . ", MIN(medie) AS n" . $procent . " FROM (SELECT medie FROM note WHERE judet = '$j' ORDER BY medie DESC LIMIT " . round($judet["GLOBAL"]["prez"] * $procent / 100) . ")")->fetch(PDO::FETCH_ASSOC);
        if($j == "B"){
            continue;
        }
        $jud[$j]["URBAN"] += $db->query("SELECT ROUND(AVG(medie), 2) AS m" . $procent . ", MIN(medie) AS n" . $procent . " FROM (SELECT medie FROM note WHERE judet = '$j' AND mediu = 'URBAN' ORDER BY medie DESC LIMIT " . round($judet["URBAN"]["prez"] * $procent / 100) . ")")->fetch(PDO::FETCH_ASSOC);
        $jud[$j]["RURAL"] += $db->query("SELECT ROUND(AVG(medie), 2) AS m" . $procent . ", MIN(medie) AS n" . $procent . " FROM (SELECT medie FROM note WHERE judet = '$j' AND mediu = 'RURAL' ORDER BY medie DESC LIMIT " . round($judet["RURAL"]["prez"] * $procent / 100) . ")")->fetch(PDO::FETCH_ASSOC);
    }
    echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";
}

// numarul de grupuri de note pe fiecare mediu
foreach($db->query("SELECT judet, mediu, FLOOR(medie * 10 / 5) * 5 AS med, COUNT(*) AS num FROM note WHERE medie > 0 GROUP BY judet, mediu, med", PDO::FETCH_OBJ) as $row) {
    $jud[$row->judet][$row->mediu]["p" . $row->med] = $row->num;
    if(!isset($jud[$row->judet]["GLOBAL"]["p" . $row->med])) {
        $jud[$row->judet]["GLOBAL"]["p" . $row->med] = $row->num;
    } else {
        $jud[$row->judet]["GLOBAL"]["p" . $row->med] += $row->num;
    }
}

$db->beginTransaction();
foreach($jud as $judet) {
    foreach($judet as $row) {
        $insert->execute($row);
    }
}
$db->commit();

echo '@' . __LINE__ . ': +' . round(1000 * (microtime(true) - $start)) . " ms\n";

echo "Am terminat calculele judetene\n";
