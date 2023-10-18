<?php

namespace ShoppingFeed\Sdk;

use ShoppingFeed\Sdk\Api\Order\UploadOrderDocument;
use ShoppingFeed\Sdk\Api\Order\UploadOrderDocumentCollection;

$credential = new Credential\Password('login', 'password');
$session    = Client\Client::createSession($credential);

$orderApi = $session->getMainStore()
    ->getOrderApi();

$uploadDocument = new UploadOrderDocument('/Users/kevinfrydman/Downloads/FA105411.pdf', UploadOrderDocument::INVOICE);
$collection = new UploadOrderDocumentCollection();
$collection->addDocument($uploadDocument);

$operation = new \ShoppingFeed\Sdk\Api\Order\OrderOperation();
$operation
    ->uploadDocuments('001-23144L16805-A', 'leroymerlin', $collection);

$orderApi->execute($operation);
