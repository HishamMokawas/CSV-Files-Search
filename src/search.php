<?php
    require_once('CSV/CSVFile.php');
    use CSV\CsvFile;

    define("DESCRIPTION", "php search.php [path] [column index] [search key]".PHP_EOL.
            "path: the path of the .csv file.".PHP_EOL.
            "column index: the index of the column you want to use for searching.".PHP_EOL.
            "search key: the value you want to search for.".PHP_EOL);
  

    $fpath = '';
    $colIndex = -1;
    $searchKey = '';

    // -----------------------------    Validation    ----------------------------------------------
    if(key_exists(1,$argv)){
        if(!is_file($argv[1])){
            die("Couldn't find path ".$fpath.", please follow the syntax below:".PHP_EOL.DESCRIPTION);
        }
        $fpath = $argv[1];
    }
    else{
        die('Missing parameters. Please follow the syntax below:'.PHP_EOL.DESCRIPTION);
    }

    if(key_exists(2,$argv)){
        if(!ctype_digit($argv[2])){
            die('The column index must be an integer. Please follow the syntax below:'.PHP_EOL.DESCRIPTION);
        }
        $colIndex = $argv[2];
    }
    else{
        die('Missing parameters. Please follow the syntax below:'.PHP_EOL.DESCRIPTION);
    }

    if(key_exists(3,$argv)){
        $searchKey = $argv[3];
    }
    else{
        die('Missing parameters. Please follow the syntax below:'.PHP_EOL.DESCRIPTION);
    }
    //-----------------------------------------------------------------------------------------------

    $file = new CsvFile($fpath, ';');

    //the method searchChunkByColumnFirst returns only the first row it finds
    try{
        $targetRow = $file->searchChunkByColumnFirst(200, function($chunk) use ($colIndex, $searchKey){
            $result = array_search($searchKey, array_column($chunk, $colIndex));
            if($result !== false){
                return $chunk[$result];
            }
            else{
                return false;
            }
    
        },false);
    
        if($targetRow){
            echo $file->formatCsvRow($targetRow).PHP_EOL;
        }
        else{
            echo "Row is not found".PHP_EOL;
        }
    }catch(Exception $excp){
        echo "Error: ".$excp->getMessage();
    }
   



    
    // we should use the method searchChunkByColumnAll in case we want to return all the matching rows.
    // try{
    //     $targetRows = $file->searchChunkByColumnAll(200, function($chunk) use ($colIndex, $searchKey){
    //         $keys = array_keys(array_column($chunk, $colIndex), $searchKey);
    //         $targetRows = [];
    //         foreach($keys as $key){
    //             $targetRows[] = $chunk[$key];
    //         }
    //         return $targetRows;
    //     });
    
    //     if($targetRows){
    //         foreach($targetRows as $row){
    //             echo $file->formatCsvRow($row).PHP_EOL;
    //         }
    //     }
    //     else{
    //         echo "No matching rows".PHP_EOL;
    //     }

    // }catch(Exception $excp){
    //     echo "Error: ".$excp->getMessage();
    // }
    
    
    
    //To process the whole file at once while searching, we can set the $count parameter of searchChunkByColumnAll,
    //or searchChunkByColumnFirst to a very large integer, or better than that, we can call the member function $file->read(),
    //then access the data $file->getData for processing.
    


 







