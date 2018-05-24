<?php
ini_set("max_execution_time",300000);
$sqlname = $_GET['mysql'] ?? false;
$username = $_GET['username'] ?? false;
$password = $_GET['password'] ?? false;
$db = $_GET['db'] ?? false;
$table = $_GET['table'] ?? false;
$test = $_GET['test'] ?? false;
$importFile = $_GET['filename'] ?? false;
$separator = $_GET['separator'] ?? "\t";
$terminator = trim($_GET['terminator']) ?? false;
$mapColumns = $_GET['mapColumns'] ?? false;
$columnMap = $_GET['columnMap'] ?? false;

if (isset($_GET['loadFile'])) {
    $data = getFileHeader($_GET['loadFile'],$separator);
    $line = is_string($data) ? $data : "SUCCESS: " . join(", ", $data);
    die($line);
}

if ($test || isset($_GET['importData'])) {
    if (!$sqlname || !$username || !$password || !$db || !$table) {
        die("Missing input parameter.");
    }
    $conn = new mysqli($sqlname, $username, $password, $db);
    if (!$conn) {
        $msg = $conn->error;
    }
    if ($test) {
        if ($conn) {
            $query = "DESCRIBE $table";
            $result = $conn->query($query);
            $columns = $result->fetch_all();
            $names = [];
            foreach($columns as $column) array_push($names,$column[0]);
            $rows = join(", ",$names);
            $msg = "SUCCESS: $rows";
        }
        die($msg);
    } else {
        $file = getFileHeader($importFile,$separator,true);
        if (!$file) die("ERROR: File doesn't exist, fool.");
        $importData = $_GET['importData'];
        $countQuery = "select count(*) from $table";
        $count = $conn->query($countQuery)->fetch_assoc()["COUNT(*)"];
        $insertQuery = "LOAD DATA LOCAL INFILE '$file' INTO TABLE $table";
        if ($separator && $separator !== '\t') $insertQuery .= " FIELDS TERMINATED BY '$separator'";
        if (trim($terminator) && $terminator !== '\n') $insertQuery .= " LINES TERMINATED BY '$terminator'";
        if ($mapColumns) {
            $colNames = [];
            $setNames = [];
            $headers = getFileHeader($importFile,$separator);
            if (!is_array($headers)) die($headers);
            $maps = explode(',',$columnMap);
            foreach($maps as $map) {
                $data = explode("=",trim($map));
                $csvColumn = $data[0];
                $dbColumn = $data[1] ?? false;
                $colIndex = array_search($csvColumn,$headers);
                if ($dbColumn && $colIndex !== false) {
                    array_push($colNames, "@col$colIndex");
                    array_push($setNames, "$dbColumn=@col$colIndex");
                }
            }
            if (count($colNames)) {
                $colNames = join(",", $colNames);
                $setNames = join(",", $setNames);
                $insertQuery .= " ($colNames) set $setNames";
            }
        }


        if ($importData) {
//            $result = $conn->query($insertQuery);
//            if (!$result) {
//                $msg = "An error occurred for the query '$insertQuery': " . $conn->error;
//            } else {
//                $count2 = $conn->query($countQuery)->fetch_assoc()["COUNT(*)"];
//                $count = $count2 - $count;
//                $msg = "Transaction successful, $count records were imported.";
//            }
            $msg = "Try again, dude.";
        } else {
            $msg = "Sample query: '$insertQuery'";
        }
        die($msg);
    }
}

function getFileHeader($file,$separator, $returnPath=false) {
    $path = false;
    $input = trim($file);
    if ($input === "") return $returnPath ? false : "ERROR: No path specified.";
    foreach([$input, ".$input"] as $test) {
        if (realpath($test) && file_exists($test)) $path = realpath($test);
    };
    if (!$path) return $returnPath ? false : "ERROR: File not found.";
    if (!is_readable($path)) return $returnPath ? false : "ERROR: File not accessible.";
    $line = fgets(fopen($path, 'r'));
    return $returnPath ? $path : explode($separator,$line);

}

?>
<html>
<head>
    <title> csv2 sql</title>
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
    <style>
        .centerDiv {
            margin: 0 auto;
            width:30%;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="centerDiv">

    <br>
    <h1> CSV to Mysql </h1>
    <p> This Php Script Will Import very large CSV files to MYSQL database in a minute</p>

    <br>
    <form class="form-horizontal" action="importer.php" method="post">
        <div class="form-group">
            <label for="mysql" class="control-label col-xs-3">Hostname:</label>
            <div class="col-xs-8">
                <input type="text" class="form-control" name="mysql" id="mysql" value="localhost">
            </div>
        </div>
        <div class="form-group">
            <label for="username" class="control-label col-xs-3">Username:</label>
            <div class="col-xs-8">
                <input type="text" class="form-control" name="username" id="username" placeholder="">
            </div>
        </div>
        <div class="form-group">
            <label for="password" class="control-label col-xs-3">Password:</label>
            <div class="col-xs-8">
                <input type="password" class="form-control" name="password" id="password" placeholder="" value="">
            </div>
        </div>
        <div class="form-group">
            <label for="db" class="control-label col-xs-3">Database:</label>
            <div class="col-xs-8">
                <input type="text" class="form-control" name="db" id="db" placeholder="">
            </div>
        </div>

        <div class="form-group">
            <label for="table" class="control-label col-xs-3">Table:</label>
            <div class="col-xs-6">
                <input type="text" class="form-control" name="table" id="table">
            </div>
            <div class="col-xs-2">
                <button class="btn btn-secondary" type="button" onclick="testConnection();">Test</button>
            </div>
        </div>
        <div class="form-group">
            <label for="columnNames" class="control-label col-xs-3">Columns:</label>
            <div class="col-xs-8">
                <input type="text" class="form-control" name="columnNames" id="columnNames" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="fileName" class="control-label col-xs-3">Filename:</label>
            <div class="col-xs-6">
                <input type="text" class="form-control" name="fileName" id="fileName" placeholder="MyData.csv">
            </div>
            <div class="col-xs-2">
                <button class="btn btn-secondary" type="button" onclick="loadFile();">Test</button>
            </div>
        </div>
        <div class="form-group">
            <label for="inputNames" class="control-label col-xs-3">Columns:</label>
            <div class="col-xs-8">
                <input type="text" class="form-control" name="inputNames" id="inputNames" readonly>
            </div>
        </div>
        <div class="form-group">
            <label for="separator" class="control-label col-xs-3">Separator:</label>
            <div class="col-xs-8">
                <input type="text" class="form-control" name="separator" id="separator" value="\t">
            </div>
        </div>
        <div class="form-group">
            <label for="terminator" class="control-label col-xs-3">EOL:</label>
            <div class="col-xs-8">
                <input type="text" class="form-control" name="terminator" id="terminator" value="">
            </div>
        </div>
        <div class="form-group">
            <div class="form-group">
                <label for="mapColumns" class="control-label col-xs-3">Map Columns:</label>
                <div class="col-xs-8">
                    <input type="checkbox" class="form-control" id="mapColumns" onclick="toggleGroup()">
                </div>
            </div>
            <div class="form-group" id="mappings" style="display: none">
                <label for="columnMap" class="control-label col-xs-3">Mapping:</label>
                <div class="col-xs-8">
                    <input type="text" class="form-control" name="columnMap" id="columnMap" placeholder="col1=ID, col2=Name, col3=LastName, col4=Age, col5=City">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="skipFirst" class="control-label col-xs-3">Skip Header:</label>
            <div class="col-xs-8">
                <input type="checkbox" class="form-control" id="skipFirst" checked>
            </div>
        </div>
        <div class="form-group">
            <label for="mapColumns" class="control-label col-xs-3">On Duplicates:</label>
            <div class="col-xs-8">
                <input type="radio" class="form-control-sm" id="ignoreData" name="mapColumns" value="" checked>
                <label for="ignoreData">Ignore</label>
                <input type="radio" class="form-control-sm" id="insertData" name="mapColumns" value="">
                <label for="insertData">Insert</label>
                <input type="radio" class="form-control-sm" id="updateData" name="mapColumns" value="">
                <label for="updateData">Update</label>
            </div>
        </div>
        <div class="form-group">
            <label for="login" class="control-label col-xs-3"></label>
            <div class="col-xs-8">
                <button type="button" class="btn btn-primary" onclick="importQuery(true);">Execute</button>
                <button class="btn btn-secondary" type="button" onclick="importQuery();">Simulate</button>
            </div>
        </div>
        <input type="hidden" name="doQuery" value="doQuery">
    </form>
</div>
<script type="text/javascript">
    function testConnection() {
        console.log("Testing connection.");
        var mysql = document.getElementById('mysql').value;
        var username = document.getElementById('username').value;
        var password = document.getElementById('password').value;
        var db = document.getElementById('db').value;
        var table = document.getElementById('table').value;
        var query = "importer.php?test=true&mysql=" + mysql +
            "&username=" + username + "&password=" + password +
            "&db=" + db + "&table=" + table;
        var data = httpGet(query);
        var msg = data.split(": ");

        console.log("Response: " + data);
        if (msg[0] === "SUCCESS") {
            document.getElementById('columnNames').value = msg[1];
        }
        window.alert(data);
    }

    function loadFile() {
        var filename = document.getElementById('fileName').value;
        var separator = document.getElementById('separator').value;
        separator = swapSeparator(separator);
        console.log("Separator is " + separator);

        var url = "importer.php?loadFile=" + filename + "&separator=" + separator;
        var data = httpGet(url);
        var msg = data.split(": ");

        console.log("Response: " + data);
        if (msg[0] === "SUCCESS") {
            //var names = msg[1].split("/[" + separator + "]+/");
            document.getElementById('inputNames').value = msg[1];
        }
        console.log("Data: " + data);
        window.alert(data);
    }

    function importQuery(importData=false) {
        var params = {
            importData: importData,
            mysql: document.getElementById('mysql').value,
            username: document.getElementById('username').value,
            password: document.getElementById('password').value,
            db: document.getElementById('db').value,
            table: document.getElementById('table').value,
            filename: document.getElementById('fileName').value,
            separator: swapSeparator(document.getElementById('separator').value),
            terminator: swapSeparator(document.getElementById('terminator').value),
            mapColumns: document.getElementById('mapColumns').value,
            columnMap: document.getElementById('columnMap').value
        };
        var queryString = Object.keys(params).map(key => key + '=' + params[key]).join('&');
        var data = httpGet("importer.php?" + queryString);
        console.log("Return data: " + data);
        window.alert(data);
    }

    function httpGet(url) {
        var xmlHttp = new XMLHttpRequest();
        xmlHttp.open( "GET", url, false ); // false for synchronous request
        xmlHttp.send(null);
        return xmlHttp.responseText;
    }

    function swapSeparator(toSwap) {
        toSwap = toSwap.replace(/\\t/g, '\t');
        toSwap = toSwap.replace(/\\n/g, '\n');
        toSwap = toSwap.replace(/\\r/g, '\r');
        toSwap = toSwap.replace(/\\f/g, '\f');
        toSwap = encodeURI(toSwap);
        return toSwap;
    }

    function toggleGroup() {
        var group = document.getElementById('mappings');
        var checkbox = document.getElementById('mapColumns');
        if (checkbox.checked === true) {
            group.style.display = "block";
        } else {
            group.style.display = "none";
        }
    }

</script>

</body>

</html>


