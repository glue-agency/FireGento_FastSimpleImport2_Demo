<?php

namespace FireGento\FastSimpleImportDemo\Console\Command\Product;

use FireGento\FastSimpleImportDemo\Console\Command\AbstractImportCommand;
use League\Csv\Reader;
use League\Csv\Statement;
use Magento\Framework\App\ObjectManagerFactory;
use Magento\ImportExport\Model\Import;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class TestCommand
 *
 * @package FireGento\FastSimpleImport2\Console\Command
 *
 */
class DeleteSelection extends AbstractImportCommand
{
    const IMPORT_FILE = "importDelete.csv";

    private $input;

    private $validSkuList;

    private $filePath;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $readFactory;

    /**
     * @var DirectoryList
     */
    private $directory_list;

    protected $_productCollectionFactory;

    /**
     * Constructor
     *
     * @param ObjectManagerFactory $objectManagerFactory
     */
    public function __construct(
        ObjectManagerFactory $objectManagerFactory,
        \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory,
        \Magento\Framework\App\Filesystem\DirectoryList $directory_list,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
    ) {

        parent::__construct($objectManagerFactory);

        $this->readFactory = $readFactory;

        $this->directory_list = $directory_list;

        $this->_productCollectionFactory = $productCollectionFactory;
    }

    protected function configure()
    {
        $this->setName('fastsimpleimportdemo:products:deleteSelection')->setDescription('Delete Products based on SKU list');

        $this->setBehavior(Import::BEHAVIOR_DELETE);
        $this->setEntityCode('catalog_product');

        $this->setDefinition([
            new InputOption('file', null, InputOption::VALUE_OPTIONAL,
                'absolute path of file to be imported for sku list'),
            new InputOption('folder', null, InputOption::VALUE_OPTIONAL,
                'absolute path of folder of multiple files to be bulk imported for sku list'),
            new InputOption('force', 'f', InputOption::VALUE_NONE,
                'Force deletion'),
        ]);

        parent::configure();
    }

    /**
     * @return array
     */
    protected function getEntities()
    {
        if(!$this->folderArgumentProvided()){
            $csvIterationObject = $this->getIterationObject();
        }else{
            foreach(glob($this->input->getOption('folder').'/*.csv') as $file){
                $this->filePath = $file;
                $csvIterationObjects[] = $this->getIterationObject();
            }
            $csvIterationObject = $this->mergeIterationObecjts($csvIterationObjects);
        }

        echo PHP_EOL.'input rows now being filtered. proceed only with sku\'s that are known in magento. we collect now all skus.'.PHP_EOL;

        $this->initSkuList();

        $data = [];
        foreach ($csvIterationObject as $row) {
            if($this->skuIsValidInMagento($row)){
                $data[] = $row;
            }else{
                echo PHP_EOL.'sku is not know in magento, so we must skip it: '.$row['sku'];
            }
        }

        echo PHP_EOL.'sku list to be deleted: '.implode(',',array_column($data,'sku')).PHP_EOL;

        if(!$this->isForced()){
            echo PHP_EOL.'this is dry-run mode, no entites returned'.PHP_EOL;
            return [[]];
        }

        echo PHP_EOL.'this is not dry-run mode, all entities returned'.PHP_EOL;
        return $data;
    }

    protected function mergeIterationObecjts($csvIterationObjects){
        $data = [];

        foreach($csvIterationObjects as $csvIterationObject){
            foreach ($csvIterationObject as $row){
                $data[] = $row;
            }
        }

        $data = array_unique($data,SORT_REGULAR);

        return $data;
    }

    protected function skuIsValidInMagento($rowValues){
        return in_array($rowValues['sku'],$this->validSkuList);
    }

    protected function readCSV()
    {
        $csvObj = Reader::createFromString($this->readFile(static::IMPORT_FILE));
        $csvObj->setDelimiter(';');
        $csvObj->setHeaderOffset(0);
        $results = (new Statement())->process($csvObj);

        return $results;
    }

    protected function getIterationObject(){
        try{
            $csvIterationObject = $this->readCSV();
        }catch (\Exception $exception){
            echo $exception->getMessage();
            return [[]];
        }
        $this->emitInputFileLog();

        return $csvIterationObject;
    }

    protected function readFile($fileName)
    {
        $path = $this->directory_list->getRoot();

        if($this->fileArgumentProvided()){
            $path = $this->getFilePath();
            $fileName = $this->getFileName();
        }

        $directoryRead = $this->readFactory->create($path);

        return $directoryRead->readFile($fileName);
    }

    protected function getFilePath(){
        return dirname($this->filePath);
    }

    protected function getFileName(){
        return basename($this->filePath);
    }

    protected function fileArgumentProvided(){
        return !is_null($this->filePath);
    }

    protected function folderArgumentProvided(){
        return $this->input->getOption('folder') && file_exists($this->input->getOption('folder'));
    }

    protected function isForced(){
        return $this->input->getOption('force');
    }

    protected function emitInputFileLog(){
        if($this->fileArgumentProvided()){
            echo PHP_EOL.'input file:'.$this->getFilePath().'/'.$this->getFileName().PHP_EOL;
        }else{
            echo PHP_EOL.'input file (default file):'.$this->directory_list->getRoot().'/'.self::IMPORT_FILE.PHP_EOL;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        if($this->input->getOption('file') && file_exists($this->input->getOption('file'))){
            $this->filePath = $this->input->getOption('file');
        }
        parent::execute($input, $output);
    }

    protected function getProductCollection()
    {
        $collection = $this->_productCollectionFactory->create();
        $collection->addAttributeToSelect('sku');
        return $collection->getColumnValues('sku');
    }

    protected function initSkuList(){
        $this->validSkuList = $this->getProductCollection();
    }
}