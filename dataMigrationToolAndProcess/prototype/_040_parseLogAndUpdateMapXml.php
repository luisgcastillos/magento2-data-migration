#!/usr/bin/env php
<?php
require __DIR__ . '/_top.inc.php';

function usage()
{
    echo "
    
    To be run after a migration attempt
    
    Will parse the log file and then update the mapping xml with excludes accordingly
    
    Usage:
    
    php -f " . basename(__FILE__) . " -- --vhostRoot=[root dir containing magento 2]
    
";
}

$type = 'data';
$logPath = $logDir . '/' . $type . 'Migration.log';
echo "\nProcessing $type\n";
$logContents = file_get_contents($logPath);

## parse out steps
preg_match_all(
    '%\[step: (?<step>.+?)\](?<log>.+?)((?=\[step)|$|\z)%s',
    $logContents,
    $steps
);
foreach ($steps['step'] as $k => $step) {
    $log = $steps['log'][$k];
    if (false !== strpos($log, 'ERROR')) {
        echo "\nerrors found in step: $step";
        processDocuments($step, $log);
        processFields($step, $log);
        processDestinationDocuments($step, $log);
        processDestinationFields($step, $log);
    }
}


echo "\nALL DONE\n";


######## FUNCTIONS ########

class xmlUpdater
{
    private $doms = [];

    private static $instance;

    private function __construct()
    {
        //singleton only
    }

    /**
     * @return xmlUpdater
     */
    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * @param string $step
     * @return DOMDocument
     */
    public function getDomByStep($step)
    {
        switch ($step) {
            case 'EAV Step':
                $mapFile = 'map-eav.xml';
                break;

            default:
                $mapFile = 'map.xml';
        }
        return $this->getDom($mapFile);
    }

    /**
     * @param $mapFile
     * @return DOMDocument
     */
    protected function getDom($mapFile)
    {
        if (!isset($this->doms[$mapFile])) {
            $xmlDom = new DOMDocument();
            $xmlDom->load($GLOBALS['vhostRoot'] . '/bin/dataMigration/' . $mapFile);
            $this->doms[$mapFile] = $xmlDom;
        }
        return $this->doms[$mapFile];
    }

    public function __destruct()
    {
        foreach ($this->doms as $mapFile => $xmlDom) {
            $xmlDom->formatOutput = true;
            $xmlDom->save($GLOBALS['vhostRoot'] . '/bin/dataMigration/' . $mapFile);
        }
    }
}

function processDocuments($step, $log)
{
    global $jiraShell;
    $jiraIssueTitlePrefix = "Magento 2 Data Migration, Step: $step";
    $subTasks = [];
    echo "\nProcessing unmapped documents in step $step\n";
    preg_match_all(
        '%\[ERROR\]: Source documents are not mapped. (?<documents>[a-zA-Z0-9_,]+)%si',
        $log,
        $sourceDocuments
    );
    if (!empty($sourceDocuments['documents'])) {
        echo "\nFound " . count($sourceDocuments['documents']) . " document lines\n";
        foreach ($sourceDocuments['documents'] as $n => $line) {
            echo "\n$line";
            $map = xmlUpdater::instance()->getDomByStep($step);
            $documentsToIgnore = explode(',', $line);
            $documentsToIgnore = array_map('trim', $documentsToIgnore);
            echo "\nFound " . count($documentsToIgnore) . " documents\n";
            $documentRulesNode = $map->getElementsByTagName('document_rules')->item(0);
            foreach ($documentsToIgnore as $i) {
                $ignoreNode = $map->createElement('ignore');
                $docNode = $map->createElement('document', $i);
                $ignoreNode->appendChild($docNode);
                $documentRulesNode->appendChild($ignoreNode);
                $subTasks[] = [
                    $jiraIssueTitlePrefix . ' , Ignored Doc: ' . $i,
                    $jiraIssueTitlePrefix . ' , Ignored Doc: ' . $i
                ];
            }
            echo "\nDone processing documents\n";
        }
//        if (!empty($subTasks)) {
//            $jiraShell->queueIssue(
//                $jiraIssueTitlePrefix . ' Ignored Documents',
//                'Documents are being ignored. These need to be checked one by one to either confirm it should be ignored or to manage proper migration',
//                $subTasks
//            );
//        }
    } else {
        echo "\nno unmapped documents found";
    }
}

function processFields($step, $log)
{
    echo "\nProcessing unmapped fields in step $step\n";
    global $jiraShell;
    $jiraIssueTitlePrefix = "Magento 2 Data Migration, Step: $step";

    preg_match_all(
        '%\[ERROR\]: Source fields are not mapped. Document: (?<document>[^.]+?)\. Fields: (?<fields>[a-zA-Z0-9_,]+)%si',
        $log,
        $sourceFields
    );
    if (!empty($sourceFields['fields'])) {
        echo "\nFound " . count($sourceFields['fields']) . " field lines\n";
        foreach ($sourceFields['fields'] as $k => $line) {
            echo "\nLine $k\n";
            $map = xmlUpdater::instance()->getDomByStep($step);
            $document = $sourceFields['document'][$k];
            $fieldsToIgnore = explode(',', $line);
            $fieldsToIgnore = array_map('trim', $fieldsToIgnore);
            echo "\nFound " . count($fieldsToIgnore) . " fields\n";
            $fieldRulesNode = $map->getElementsByTagName('field_rules')->item(0);
            foreach ($fieldsToIgnore as $i) {
                $ignoreNode = $map->createElement('ignore');
                $docNode = $map->createElement('field', "$document.$i");
                $ignoreNode->appendChild($docNode);
                $fieldRulesNode->appendChild($ignoreNode);
                $subTasks[] = [
                    $jiraIssueTitlePrefix . ' , Ignored Field: ' . $i,
                    $jiraIssueTitlePrefix . ' Document: ' . $document . ' , Ignored Field: ' . $i
                ];
            }
            echo "\nDone processing fields\n";
        }
//        if (!empty($subTasks)) {
//            $jiraShell->queueIssue(
//                $jiraIssueTitlePrefix . ' Ignored Fields',
//                'Fields are being ignored. These need to be checked one by one to either confirm it should be ignored or to manage proper migration',
//                $subTasks
//            );
//        }
    } else {
        echo "\nno unmapped fields found";
    }
}

function processDestinationDocuments($step, $log)
{
    global $jiraShell;
    $jiraIssueTitlePrefix = "Magento 2 Data Migration, Step: $step";
    $subTasks = [];
    echo "\nProcessing unmapped documents in step $step\n";
    preg_match_all(
        '%\[ERROR\]: Destination documents are not mapped. (?<documents>[a-zA-Z0-9_,]+)%si',
        $log,
        $sourceDocuments
    );
    if (!empty($sourceDocuments['documents'])) {
        echo "\nFound " . count($sourceDocuments['documents']) . " document lines\n";
        foreach ($sourceDocuments['documents'] as $n => $line) {
            echo "\n$line";
            $map = xmlUpdater::instance()->getDomByStep($step);
            $documentsToIgnore = explode(',', $line);
            $documentsToIgnore = array_map('trim', $documentsToIgnore);
            echo "\nFound " . count($documentsToIgnore) . " documents\n";
            $documentRulesNode = $map->getElementsByTagName('document_rules')->item(1);
            foreach ($documentsToIgnore as $i) {
                $ignoreNode = $map->createElement('ignore');
                $docNode = $map->createElement('document', $i);
                $ignoreNode->appendChild($docNode);
                $documentRulesNode->appendChild($ignoreNode);
                $subTasks[] = [
                    $jiraIssueTitlePrefix . ' , Ignored Doc: ' . $i,
                    $jiraIssueTitlePrefix . ' , Ignored Doc: ' . $i
                ];
            }
            echo "\nDone processing documents\n";
        }
//        if (!empty($subTasks)) {
//            $jiraShell->queueIssue(
//                $jiraIssueTitlePrefix . ' Ignored Documents',
//                'Documents are being ignored. These need to be checked one by one to either confirm it should be ignored or to manage proper migration',
//                $subTasks
//            );
//        }
    } else {
        echo "\nno unmapped documents found";
    }
}

function processDestinationFields($step, $log)
{
    echo "\nProcessing unmapped fields in step $step\n";
    global $jiraShell;
    $jiraIssueTitlePrefix = "Magento 2 Data Migration, Step: $step";

    preg_match_all(
        '%\[ERROR\]: Destination fields are not mapped. Document: (?<document>[^.]+?)\. Fields: (?<fields>[a-zA-Z0-9_,]+)%si',
        $log,
        $sourceFields
    );
    if (!empty($sourceFields['fields'])) {
        echo "\nFound " . count($sourceFields['fields']) . " field lines\n";
        foreach ($sourceFields['fields'] as $k => $line) {
            echo "\nLine $k\n";
            $map = xmlUpdater::instance()->getDomByStep($step);
            $document = $sourceFields['document'][$k];
            $fieldsToIgnore = explode(',', $line);
            $fieldsToIgnore = array_map('trim', $fieldsToIgnore);
            echo "\nFound " . count($fieldsToIgnore) . " fields\n";
            $fieldRulesNode = $map->getElementsByTagName('field_rules')->item(1);
            foreach ($fieldsToIgnore as $i) {
                $ignoreNode = $map->createElement('ignore');
                $docNode = $map->createElement('field', "$document.$i");
                $ignoreNode->appendChild($docNode);
                $fieldRulesNode->appendChild($ignoreNode);
                $subTasks[] = [
                    $jiraIssueTitlePrefix . ' , Ignored Field: ' . $i,
                    $jiraIssueTitlePrefix . ' Document: ' . $document . ' , Ignored Field: ' . $i
                ];
            }
            echo "\nDone processing fields\n";
        }
//        if (!empty($subTasks)) {
//            $jiraShell->queueIssue(
//                $jiraIssueTitlePrefix . ' Ignored Fields',
//                'Fields are being ignored. These need to be checked one by one to either confirm it should be ignored or to manage proper migration',
//                $subTasks
//            );
//        }
    } else {
        echo "\nno unmapped fields found";
    }
}

