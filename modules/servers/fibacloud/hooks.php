<?php
use WHMCS\Database\Capsule;
use WHMCS\View\Menu\Item as MenuItem;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


add_hook('ClientAreaPrimarySidebar', 1, function($primarySidebar)
{
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $serviceid = isset($_GET['id']) ? $_GET['id'] : '';
    
    if ($serviceid && $action) {
        if ($serviceDetailsActions = $primarySidebar->getChild('Service Details')) {
            $serviceDetailsActions->addChild('Interface Management', [
                'label' => 'Interface Management',
                'uri' => '/clientarea.php?action=productdetails&id=' . $serviceid . '&customAction=interfaces',
                'order' => 1,
                'icon' => 'fa fa-network-wired',
            ]);

            $serviceDetailsActions->addChild('Rebuild VM', [
                'label' => 'Rebuild VM',
                'uri' => '/clientarea.php?action=productdetails&id=' . $serviceid . '&customAction=rebuild',
                'order' => 2,
                'icon' => 'fa fa-sync',
            ]);
        }
    }
});
add_hook('ClientAreaPrimarySidebar', 1, function($primarySidebar) {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $serviceid = isset($_GET['id']) ? $_GET['id'] : '';
    $customAction = isset($_GET['customAction']) ? $_GET['customAction'] : '';

    if ($serviceid && $action) {
        if ($serviceDetailsActions = $primarySidebar->getChild('Service Details Overview')) {
            $interfacesMenuItem = $serviceDetailsActions->addChild('Interface Management', [
                'label' => 'Interface Management',
                'uri' => '/clientarea.php?action=productdetails&id=' . $serviceid . '&customAction=interfaces',
                'order' => 20,
                'icon' => 'fa fa-network-wired',
            ]);
            
            $ipsMenuItem = $serviceDetailsActions->addChild('RDNS Management', [
                'label' => 'RDNS Management',
                'uri' => '/clientarea.php?action=productdetails&id=' . $serviceid . '&customAction=ips',
                'order' => 30,
                'icon' => 'fa fa-pencil',
            ]);

            $storageMenuItem = $serviceDetailsActions->addChild('Storage Management', [
                'label' => 'Storage Management',
                'uri' => '/clientarea.php?action=productdetails&id=' . $serviceid . '&customAction=storage',
                'order' => 40,
                'icon' => 'fa fa-database',
            ]);

            $rebuildMenuItem = $serviceDetailsActions->addChild('Rebuild VM', [
                'label' => 'Rebuild VM',
                'uri' => '/clientarea.php?action=productdetails&id=' . $serviceid . '&customAction=rebuild',
                'order' => 50,
                'icon' => 'fa fa-sync',
            ]);

            if ($customAction == 'interfaces') {
                $interfacesMenuItem->setClass('active');
            } elseif ($customAction == 'storage') {
                $storageMenuItem->setClass('active');
            } elseif ($customAction == 'rebuild') {
                $rebuildMenuItem->setClass('active');
            } elseif ($customAction == 'ips') {
                $ipsMenuItem->setClass('active');
            }
        }
    }
});
