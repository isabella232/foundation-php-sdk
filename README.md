# TMMData Foundation SDK

The TMMData Foundation SDK enables you to work with the TMMData Foundation V3 API. Providing the ability to programatically perform actions in the TMMData Foundation Platform.

## Requirements ##
* [PHP 7.2.0 or higher](https://www.php.net/)
* [TMMData Foundation Account](https://www.tmmdata.com)

## Installation ##

First you must generate an API Key from within Foundation.
1. Click your User Name in the top right corner
1. Select **Account Settings**
1. Click **Generate Token**
1. Click the drop down arrow on the right for the newly generated token
1. Enter a descriptive Name for what it's going to be used for
1. Enter an expiration date for the Token.
1. Click **Save Token**

Download the TMMData Foundation SDK [Here](https://github.com/tmmdata/), and include the package.

```php
require_once '/path/to/your-project/TMMData/Foundation.class.php';
```


## Custom Code ##

When executing as Custom PHP in The TMMData Foundation Platform, the SDK is already included as well as a few helpful GUIDs to aid in your development.

In a Data Process, a JSON object is passed to your script that includes the **source** guid of the table that the DataProcess is tied to, and your **user** guid. 
```JSON
{"source":"1234567890123","user":"abcdefghijklm"}
```

In a Custom Field the JSON object contains **field** name of the Custom Field, the **row** id of the current record, and **data** for the rest of the row. You can save changes made to values in the data array by adding a commit = 1 flag at the top level of the JSON object.
```JSON
{"source":"1234567890123","field":"r","row":"45","data":{"sys_id":"1","sys_cdate":"2020-03-27 10:35:51","sys_udate":"2020-03-27 10:35:55","sys_user_id":"1","Field1":"Value","Field2":"Value2","Field3":"Value3","FieldN":"ValueN"}}
```

To easily use these values, json decode the passed argument.

```php
$obj = json_decode($argv[1],TRUE);
```

## API and SDK Return Structure ##

The V3 API follows, where appropriate, the [ JSON:API](https://jsonapi.org/) specifications. As such, the returns from the SDK will have the same structure and metadata. To eliminate the metadata from the return, simply tell Foundation not to include it.

```php
$foundation->setMeta(FALSE);
```

## Examples ##

Many of the following examples use the **$obj** variable passed in Custom Code. Replace **$obj['source']** with the source_guid of the table you would like to interact with. The source_guid can be found in the **Frame** tool in Foundation under **General Information**.

### Basic Example
To use the SDK, use the [Namespace](https://www.php.net/manual/en/language.namespaces.php) TMMData\Foundation and instantiate a new Foundation object. From this foundation object you can request any Resource/Endpoint to interact with.

```php
use TMMData\Foundation;

$foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
$source = $foundation->getResource('SourceInterface',$obj['source']);
$table_name = $source->getName();
```

### Fetching Records

While it is possible to use a custom view definition when pulling records, we recommend that you create your view in **Fix** and use that view_id and page through the results using the **limit** and **page** parameters. If you need more than 10,000 records in one responce, it is recommended that you set **file_type** to **_txt_** or **_csv_** and set **delivery** to **print** or **file**.

**_Note_**: Default limit for **jsonapi** is 5000 records. If a limit of more than 1000 records is requested the job will be queued. If running via Custom Code in TMMData, this has the potential to cause dead locks.

```php
use TMMData\Foundation;

try {
  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $source = $foundation->getResource('SourceInterface',$obj['source']);
  $records = $source->records([
    'limit' => $limit,
    'view'  => $view_id,
    'page'  => $page_number
  ]);
  foreach ($records['data'] as $record) {
    echo $record['id'].PHP_EOL;
    foreach ($record['attributes'] as $field => $data) {
      echo $field.': '.$data.PHP_EOL;
    }
    echo "\n";
  }
} catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;
}
```

### Single Record Insert

Insert requires a single parameter that is an associative array with a **record** element defined as an associative array that is key value pairs of Field Name => Value to insert.

```php
use TMMData\Foundation;

try {
  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $source = $foundation->getResource('SourceInterface',$obj['source']);
  $result = $source->insert(['record' => [
    'Field1' => 'Value',
    'Field2' => 'Value2',
    'Field3' => 'Value3',
    'FieldN' => 'ValueN'
  ]]);
  $sys_id = $result['data']['attributes']['result'];
  echo $sys_id;
} catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;  
}
```

### Multi Record Insert

Insert requires a single parameter that is an associative array with a **batch** element defined as an array of associative array that is key value pairs of Field Name => Value to insert.

```php
use TMMData\Foundation;

try {
  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $source = $foundation->getResource('SourceInterface',$obj['source']);
  $result = $source->insert(['batch' => [
    [
      'Field1' => 'Value',
      'Field2' => 'Value2',
      'Field3' => 'Value3',
      'FieldN' => 'ValueN'
    ],
    [
      'Field1' => 'Record2',
      'Field2' => 'Data',
      'Field3' => 'More Data',
      'FieldN' => 'abcdef'
    ],
  ]]);
  $sys_ids = $result['data']['attributes']['result'];
  echo $sys_ids;
  
} catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;  
}
```

### FileImport

File import is the recommended way to insert multiple records at once. [File Triggers](http://docs.tmmdata.com/m/25951/l/292206-auto-importer-triggers) can be placed on the filename to perform various tasks on import. The same things can be acheived using the **import_options** array:
* autocommit
  * Values
    * TRUE
    * FALSE 
  * Do not use a transaction for insert
* truncate
  * Values
    * TRUE
    * FALSE 
  * Truncates the table before importing the files
* empty_records
  * Values
    * TRUE
    * FALSE 
  * Include empty records from the file
* skip
  * Number of Lines to skip before start of import
* skip_col
  * Array of 1 based column indexes to skip
* bp
  * GUID of DataProcess to run when Import is complete
* prebp
  * GUID of DataProcess to run before Import starts
* merge
  * Array of 1 based column indexes to use as a unique key to insert new records and update existing records
* ins_new_only
  * Array of 1 based column indexes to use as a unique key to insert new records
* custom_fields
  * Values
    * TRUE
    * FALSE
  * Run custom fields on import
* return_id
  * Values
    * TRUE
    * FALSE
  * Return the system identifiers for each row inserted
* force_null
  * Values
    * TRUE
    * FALSE
  * Force empty values to be NULLs in the Database, default is an empty string
* sheets
  * Array of Associative arrays that contain:
    * **sheet_no** 0 based sheet index to import
    * **sheet_guid** source_guid for a separate table to import the associated sheet into
* file_encoding
  * specify the character encoding of the file if something other than **UTF-8**
  * EX:
    * UTF-16LE
    * WINDOWS-1251
* nopadtxt
  * Values
    * TRUE
    * FALSE
  * Do not add padding to newly created text fields
* nopadnum
  * Values
    * TRUE
    * FALSE
  * Do not add padding to newly created number fields
* addnewcols
  * Values
    * TRUE
    * FALSE
  * Automatically add new fields to the table
* adjustfields
  * Values
    * TRUE
    * FALSE
  * Adjust/Expand field sizes to accomidate new data. We Recommend also using **nopadnum** and **nopadtxt**

**_Note_**: If **get_result** is passed the the call waits for the import to finish before responding, up to 10 minutes. This helps prevent deadlocks when importing files from Custom Code.

```php
use TMMData\Foundation;

$obj = json_decode($argv[1],TRUE);

try {
  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $source = $foundation->getResource('SourceInterface',$obj['source']);
  $result = $source->import([
    'file'           => curl_file_create('/path/to/your/file.txt'),
    'get_result'     => 1,
    'import_options' => [
      'truncate' => TRUE,
      'skip_col' => [1,5,10],
      'sheets'   => [[
        'sheet_no' => 0,
      ],
      [
        'sheet_no' => 1,
        'sheet_guid' => '2345678901234'
      ]]
    ]
  ]);
  echo $result['data']['attributes']['result']['output'][0]['message'];
}catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;  
}
```
### Updating records

Outer most array is an array of Filter Groups, that will be joined with an OR. Second is an array of Filters, that will be joined with an AND. InnerMost is an actual Filter that contains the **field**, **cond**, and **val**.

Valid option for **cond**:
* eq - Equals
* ne - Not Equals
* lt - Less Than
* le - Less Than or Equal
* gt - Greater Than
* ge - Greater Than or Equal
* bw - Begins With
* ew - Ends With
* cn - Contains
* bn - Does Not Begin With
* en - Does Not End With
* nc - Does Not Contain
* in - Is In (comma separated list)
* ni - Is not In (comma separated list)
* reg - Regular Expression
* nreg - Does not match Regular Expression
* nu - Is Null
* nn - Is Not Null
  
```php
use TMMData\Foundation;

try{
  $obj = json_decode($argv[1],TRUE);

  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $source = $foundation->getResource('SourceInterface',$obj['source']);
  $source->startMultiAction();
  $source->setFilters([//Filter Group. 
    [//Filters
      [// Filter
        'field'=>'Field3',
        'cond'=>'eq',
        'val'=>'123'
      ]// End Filter
    ]// End Filters
  ]// End Filter Group
  );
  $source->update(['Field1' => 'Value','Field2'=>'Value2','FieldN'=>'ValueN'],NULL,TRUE,TRUE);
  $result = $source->commitMultiAction();
  var_dump($result);
} catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;  
}
```

### Deleting records

Filters work the same as Update.

```php
use TMMData\Foundation;

try{
  $obj = json_decode($argv[1],TRUE);

  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $source = $foundation->getResource('SourceInterface',$obj['source']);
  $source->startMultiAction();
  $source->setFilters([//Filter Group
    [//Filters
      [// Filter
        'field'=>'Field3',
        'cond'=>'eq',
        'val'=>'1234'
      ]// End Filter
    ]// End Filters
  ]// End Filter Group
  );
  $source->delete();
  $result = $source->commitMultiAction();
  var_dump($result);
} catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;  
}
```

### Running a DataProcess

Data Processes can be Queued or Run directly. When calling **run** it will run the DP Directly and wait for response before returning. When calling **queue** it will Queue the DP to run as a Job and return immediately.

```php
use TMMData\Foundation;

try{
  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $dp = $foundation->getResource('BusinessRuleGroup',$dp_guid);
  //Queue the DP to run as a Job and return immediately
  $result = $dp->queue();
  var_dump($result);
  //Run the DP Directly and wait for response
  $result = $dp->run();
  echo $result['data']['attributes']['result']['message'];
} catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;  
}
```

### Running a Saved Export

Result will depend on what kind of options are saved on the Export

```php
use TMMData\Foundation;

try{
  $foundation = new Foundation(['host' => $host, 'apikey' => $apikey]);
  $api = $foundation->getResource('SavedAPI',$api_guid);
  $result = $api->run();
  var_dump($result);
} catch (Exception $e) {
  echo $e->getCode().PHP_EOL;
  echo $e->getMessage().PHP_EOL;  
}
```