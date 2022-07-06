<?php

namespace CSV;

use Exception;

class CsvFile{
    
    private $fileName;
    private $length;
    private $separator;
    private $enclosure;
    private $escape;
    private $terminator;
    private $headerCols;
    private $data = [];

    /**
     * @param string $fileName 
     * The file path
     * @param string $terminator
     * The additional string at the end of every row. This parameter is used to get the data of the last column correctly, and to format rows. 
     * @param int $length
     * Must be greater than the longest line (in characters) to be found in the CSV file (allowing for trailing line-end characters). Otherwise the line is split in chunks of length characters, unless the split would occur inside an enclosure.
     * Omitting this parameter (or setting it to 0, or null in PHP 8.0.0 or later) the maximum line length is not limited, which is slightly slower.
     * @param string $separator
     * The optional separator parameter sets the field separator (one single-byte character only).
     * @param string $enclosure 
     * The optional enclosure parameter sets the field enclosure character (one single-byte character only).
     * @param string $escape
     * The optional escape parameter sets the escape character (at most one single-byte character). An empty string ("") disables the proprietary escape mechanism.
     */
    public function __construct($fileName, $terminator="", $length = null, $separator = ',', $enclosure = "\"", $escape = "\\")
    {
        $this->fileName = $fileName;
        $this->length = $length;
        $this->separator = $separator;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
        $this->terminator = $terminator;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getHeaderColumns(){
        return $this->headerCols;
    }
    
    /**
     * Read the whole file, and store it at the $data property
     * @param bool $headers
     * Specify weither headers are included with the file or not. Setting it to true will assign the first row to the $headerCols property instead of adding it to the $data property.
     */
    public function readAll($headers = false)
    {
        $file = fopen($this->fileName, 'r');
        $firstRow = fgetcsv($file, $this->length, $this->separator, $this->enclosure, $this->escape);
        
        if(!$firstRow){//if failed
            fclose($file);
            return False;
        }   
        
        //removing the terminator from the end of the last column.
        $firstRow[array_key_last($firstRow)] = substr(end($firstRow), 0, strlen(end($firstRow)) - strlen($this->terminator));
        
        if($headers){
            $this->headerCols = $firstRow;
            $rowsCount = 0;
        }
        else{
            $this->data[] = $firstRow;
            $rowsCount = 1;
        }

        $colsCount = count($firstRow);
        
        while(!feof($file)){
            $row = fgetcsv($file, $this->length, $this->separator, $this->enclosure, $this->escape); 
            
            if($row){
                if(count($row) != $colsCount){//columns mismatch
                    fclose($file);
                    throw new Exception("Columns at row ".($rowsCount + 1)." don't match columns at row 0");
                }

                //removing the terminator from the end of the last column.
                $row[array_key_last($row)] = substr(end($row), 0, strlen(end($row)) - strlen($this->terminator));
                $rowsCount++;
                $this->data[] = $row;
            }
            else{
                fclose($file);
                throw new Exception("Some error happened while reading the file");
            }              
        }
        
        fclose($file);
        return false;
    }
    
    /**
     * Search the csv file, and return the first row found. It processes only a chunk of rows at a time, in order to prevent running out of memory in case of large files.
     * @param int $count
     * The number of rows to process at a time (The chunk size).
     * @param \Closure $searchFunction
     * The search function to be applyed on the chunk. It should return the target row from the chunk.
     * @param bool $headers
     * Specify weither headers are included with the file or not. Setting it to true will assign the first row to the $headerCols property instead of adding it to the $data property.
     * @return array|false
     * Target row if it is found, or false
     */
    public function searchChunkByColumnFirst($count, $searchFunction, $headers = false)
    {
        $file = fopen($this->fileName, 'r');
        $firstRow = fgetcsv($file, $this->length, $this->separator, $this->enclosure, $this->escape);
        
        if(!$firstRow){//if failed
            fclose($file);
            return False;
        }   
        
        //removing the terminator from the end of the last column.
        $firstRow[array_key_last($firstRow)] = substr(end($firstRow), 0, strlen(end($firstRow)) - strlen($this->terminator));
        
        $chunk = [];
        if(!$headers){//then we want to consider the first row.
            $chunk[] = $firstRow;
            $rowsCount = 1;
            $r=1;
        }
        else{
            $rowsCount = 0;//we don't count the header row.
            $r=0;
        }
        
        $colsCount = count($firstRow);

        while(!feof($file)){
            
            while(!feof($file) && $r < $count){
                $row = fgetcsv($file, $this->length, $this->separator, $this->enclosure, $this->escape); 

                if($row){//if it is not empty
                    if(count($row) != $colsCount){//columns mismatch
                        fclose($file);
                        throw new Exception("Columns at row ".($rowsCount + 1)." don't match columns at row 0");
                    }

                    //removing the additional terminator from the end of the last column.
                    $row[array_key_last($row)] = substr(end($row), 0, strlen(end($row))- strlen($this->terminator));
                    
                    $rowsCount++;
                    $r++;
                    $chunk[] = $row;
                }
                else{
                    fclose($file);
                    throw new Exception("Some error happened while reading the file");
                }
            }

            $targetRow = $searchFunction($chunk);
            if($targetRow !== false){
                fclose($file);
                return $targetRow;
            }
    
            $chunk = [];//prepear the chuck for the next round.
            $r = 0;
        }

        fclose($file);
        return false;
    }

     /**
      * Search the csv file, and return all rows found. It processes only a chunk of rows at a time, in order to prevent running out of memory in case of large files. However, since this function returns all the matching rows, we still may run out of memory if the matching rows are too many.
     * @param int $count
     * The number of rows to process at a time (The chunk size).
     * @param \Closure $searchFunction
     * The search function to be applyed on the chunk. It should return the target row from the chunk.
     * @param bool $headers
     * Specify weither headers are included with the file or not. Setting it to true will assign the first row to the $headerCols property instead of adding it to the $data property.
     * @return array|false
     * Target row if it is found, or false
     */
    public function searchChunkByColumnAll($count, $searchFunction, $maxRows=false, $headers = false)
    {
        $file = fopen($this->fileName, 'r');
        $firstRow = fgetcsv($file, $this->length, $this->separator, $this->enclosure, $this->escape);
        
        if(!$firstRow){//if failed
            fclose($file);
            return False;
        }   

        //removing the additional terminator from the end of the last column.
        $firstRow[array_key_last($firstRow)] = substr(end($firstRow), 0, strlen(end($firstRow)) - strlen($this->terminator));

        $chunk = [];
        if(!$headers){//then we want to consider the first row.
            $chunk[] = $firstRow;
            $rowsCount = 1;
            $r=1;
        }
        else{
            $rowsCount = 0;//we don't count the header row.
            $r=0;
        }
        
        $colsCount = count($firstRow);
        $targetRows = [];
        while(!feof($file)){
           
            while(!feof($file) && $r < $count){
                $row = fgetcsv($file, $this->length, $this->separator, $this->enclosure, $this->escape); 

                if($row){//if it is not empty
                    if(count($row) != $colsCount){//columns mismatch
                        fclose($file);
                        throw new Exception("Columns at row ".($rowsCount + 1)." don't match columns at row 0");
                    }
                    $row[array_key_last($row)] = substr(end($row), 0, strlen(end($row))- strlen($this->terminator));
                    $rowsCount++;
                    $r++;
                    $chunk[] = $row;
                }
                else{
                    fclose($file);
                    throw new Exception("Some error happened while reading the file");
                }
            }
            
            $searchResult = $searchFunction($chunk);
            if($searchResult !== false){
                if(is_array($searchResult) && count($searchResult)> 0){
                    if(is_array($searchResult[array_key_first($searchResult)])){
                        //then we have an array of arrays
                        foreach($searchResult as $result){
                            $targetRows[] = $result;
                        }
                    }
                    else{//we have only one row
                        $targetRows[] = $searchResult;
                    }
                }
            }
            
            $chunk = [];//clear the $chunk and prepear it for the next round.
            $r = 0;
        }

        fclose($file);
        if($targetRows){
            return $targetRows;
        }

        return false;
    }

     /**
     * Convert a row represented by an array to a string
     * @param array $row
     * The row to format 
     * @return string
     * The row as a string
     */
    public function formatCsvRow($row){
        $strRow = "";
        for($i = 0 ; $i < array_key_last($row); $i++){
            $strRow.=$row[$i].$this->separator;
        }
        $strRow.= $row[array_key_last($row)].$this->terminator;
        return $strRow;
    }
}
