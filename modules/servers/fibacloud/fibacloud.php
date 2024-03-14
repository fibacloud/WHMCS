<?php
use WHMCS\Database\Capsule;
use WHMCS\Module\Server\CustomAction;
use WHMCS\Module\Server\CustomActionCollection;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function fibacloud_MetaData() {
    return array(
        'DisplayName' => 'FibaCloud',
        'APIVersion' => '1.1', 
        'RequiresServer' => true,
    );
}

function fibacloud_ConfigOptions() {
        return [
        'Product ID' => [
            'Type' => 'dropdown',
            'Options' => [
                '16' => "Shared 1",
                '17' => "Shared 2",
                '21' => "Shared 3",
                '22' => "Shared 4",
                '23' => "Shared 5",
                '24' => "Shared 6",
                '25' => "Shared 7",
                '26' => "Shared 8",
                '49' => "Dedicated 1",
                '50' => "Dedicated 2",
                '51' => "Dedicated 3",
                '52' => "Dedicated 4",
                '53' => "Dedicated 5",
                '54' => "Dedicated 6",
                '55' => "Dedicated 7",
                '56' => "Dedicated 8",
                '57' => "High Memory 1",
                '58' => "High Memory 2",
                '59' => "High Memory 3",
                '60' => "High Memory 4",
            ],
            'Description' => 'Please select the product ID.',
        ],
        'Promo Code' => [
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Enter promo code if applicable.',
        ],
    ];
}

function callAPI($method, $url, $username, $password, $data = [], $headers = []) {
    $curl = curl_init();
    $method = strtoupper($method);
    $authHeader = 'Authorization: Basic ' . base64_encode($username . ":" . $password);
    $defaultHeaders = [$authHeader, 'Content-Type: application/json'];

    switch ($method) {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case "GET":
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
            }
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            if (!empty($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case "DELETE":
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            break;
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    $httpStatusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($error) {
        throw new Exception("CURL Error: $error");
    }

    if ($httpStatusCode >= 400) {
        $responseArr = json_decode($response, true);
        $errorMessage = $responseArr['message'] ?? 'An unknown error occurred';
        throw new Exception("API Request Failed: HTTP $httpStatusCode - $errorMessage");
    }

    return json_decode($response, true);
}

function fibacloud_CreateAccount(array $params) {
    try {
        $apiUrl = 'https://cloud.fibacloud.com/api';
        $username = $params['serverusername'];
        $password = $params['serverpassword'];
        $apiCycle = $params['billingcycle'];

        $productId = $params['configoption1'];
        $promocode = isset($params['configoption2']) ? $params['configoption2'] : '';
        $selectedOsName = $params['configoptions']['OS'];
        $hostname = $params['domain'];
        
        $billingCycle = $params['model']->billingcycle;
        $cycleMap = [
            'Monthly' => 'm',
            'Quarterly' => 'q',
            'Semi-Annually' => 's',
            'Annually' => 'a'
        ];
        $cycle = isset($cycleMap[$billingCycle]) ? $cycleMap[$billingCycle] : 'm';

        $osInfoResponse = callAPI('GET', "{$apiUrl}/order/{$productId}", $username, $password);
        if (!$osInfoResponse || !isset($osInfoResponse['product'])) {
            throw new Exception('Failed to retrieve OS information.');
        }

        $templateId = null;
        foreach ($osInfoResponse['product']['config']['forms'] as $form) {
            if ($form['title'] == 'OS') {
                $templateId = $form['id'];
                break;
            }
        }
        if (!$templateId) {
            throw new Exception('Template ID could not be found.');
        }

        $osId = null;
        foreach ($osInfoResponse['product']['config']['forms'] as $form) {
            if ($form['title'] == 'OS') {
                foreach ($form['items'] as $item) {
                    if ($item['title'] == $selectedOsName) {
                        $osId = $item['id'];
                        break;
                    }
                }
            }
        }
        if (!$osId) {
            throw new Exception('OS ID could not be found for the selected OS.');
        }

        $vmCreateResponse = callAPI('POST', "{$apiUrl}/order/instances/{$productId}", $username, $password, ["cycle" => $cycle, "domain" => $hostname, "promocode" => $promocode, "custom" => [$templateId => $osId]]);
        if (!$vmCreateResponse || isset($vmCreateResponse['error'])) {
            throw new Exception($vmCreateResponse['error'] ?? 'Failed to create VM.');
        }

        if(isset($vmCreateResponse['items'][0]['id'])) {
            $orderId = $vmCreateResponse['items'][0]['id'];
            $vmId = null;
            $startTime = time();
            while (!$vmId && (time() - $startTime < 120)) {
                $vmDetailsResponse = callAPI('GET', "{$apiUrl}/service/{$orderId}/vms", $username, $password);
                if (isset($vmDetailsResponse['vms']) && !empty($vmDetailsResponse['vms'])) {
                    $vmId = array_key_first($vmDetailsResponse['vms']);
                    break;
                }
                sleep(15);
            }

            if (!$vmId) {
                throw new Exception('VM details could not be retrieved within the specified time.');
            }

            $vmInfo = null;
            $isVmRunning = false;
            $startTime = time();
            while (!$isVmRunning && (time() - $startTime < 120)) {
                $vmInfo = callAPI('GET', "{$apiUrl}/service/{$orderId}/vms/{$vmId}", $username, $password);
                if (isset($vmInfo['vm']) && $vmInfo['vm']['status'] === 'running') {
                    $isVmRunning = true;
                    break;
                }
                sleep(15);
            }

            if (!$isVmRunning) {
                throw new Exception('VM is not in running status within the expected time.');
            }

            $updateData = [
                'serviceid' => $params['serviceid'],
                'dedicatedip' => $vmInfo['vm']['ipv4'],
                'assignedips' => $vmInfo['vm']['ipv4'] . "\n" . $vmInfo['vm']['ipv6'],
                'serviceusername' => $vmInfo['vm']['username'],
                'servicepassword' => $vmInfo['vm']['password'],
                'domain' => $vmInfo['vm']['label'],
                'disklimit' => $vmInfo['vm']['disk'],
                'notes' => "orderId: $orderId, vmId: $vmId",
            ];

            $results = localAPI('UpdateClientProduct', $updateData);
            if ($results['result'] !== 'success') {
                throw new Exception('Failed to update product details: ' . $results['message']);
            }

            $updateOrderStatus = localAPI('UpdateClientProduct', [
                'serviceid' => $params['serviceid'],
                'status' => 'Active',
            ]);

            if ($updateOrderStatus['result'] !== 'success') {
                throw new Exception('Failed to update order status to Active: ' . $updateOrderStatus['message']);
            }

        } else {
            throw new Exception('Order ID could not be retrieved.');
        }

        return 'success';
    } 
    catch (Exception $e) {
        logModuleCall(
            'fibacloud',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}

function fibacloud_getOrderAndVmIdFromNotes($serviceId) {
    $serviceData = Capsule::table('tblhosting')
                           ->where('id', $serviceId)
                           ->first();

    $notes = $serviceData->notes;
    $matches = [];
    preg_match('/orderId: (\w+), vmId: (\w+)/', $notes, $matches);

    if (!empty($matches)) {
        return ['orderId' => $matches[1], 'vmId' => $matches[2]];
    }

    return null;
}

function fibacloud_SuspendAccount(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];
        
        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/shutdown";
        
        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword']);
    
        if (isset($response['status']) && $response['status'] === true) {
            return 'success';
        } else {
            return 'An error occurred during suspension: ' . ($response['message'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function fibacloud_UnsuspendAccount(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];
        
        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/start";
        
        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword']);
        
        if (isset($response['status']) && $response['status'] === true) {
            return 'success';
        } else {
            return 'An error occurred during unsuspension: ' . ($response['message'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

function fibacloud_TerminateAccount(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];
        
        $apiUrl = "https://cloud.fibacloud.com/api/service/instances/$orderId/cancel";
        
        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword'], [
            'immediate' => 'true',
            'reason' => 'terminated by WHMCS'
        ]);
        
        if (isset($response['info']) && (in_array('cancell_sent', $response['info']) || in_array('cancelled_already', $response['info']))) {
            return 'success';
        } else {
            return 'An error occurred during termination: ' . json_encode($response);
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_ChangePackage(array $params){
    try {
        $apiUrl = 'https://cloud.fibacloud.com/api';
        $username = $params['serverusername'];
        $password = $params['serverpassword'];
        $apiCycle = $params['billingcycle'];
        
        $serviceId = $params['serviceid'];
        $newPackageId = $params['configoption1'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];
        
        $billingCycle = $params['model']->billingcycle;
        $cycleMap = [
            'Monthly' => 'm',
            'Quarterly' => 'q',
            'Semi-Annually' => 's',
            'Annually' => 'a'
        ];
        $cycle = isset($cycleMap[$billingCycle]) ? $cycleMap[$billingCycle] : 'm';
        
        $postData = [
            "package" => $newPackageId,
            "cycle" => $cycle,
            "send" => "true",
        ];
        
        $response = callAPI('POST', "{$apiUrl}/service/$orderId/upgrade", $username, $password, $postData);
        
        if (!isset($response['info']) || !in_array('upgrade_order_success', $response['info'])) {
            throw new Exception("Package upgrade failed: " . json_encode($response));
        }
        
    } catch (Exception $e) {
        logModuleCall(
            'fibacloud',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

function fibacloud_AdminCustomButtonArray($params) {
    $vmStatus = fibacloud_GetVmStatus($params);
    $buttons = [];
    if ($vmStatus == 'running') {
    $buttons = [
        'Reset VM' => 'restartVM',
        'Reboot VM' => 'rebootVM',
        'Shutdown VM' => 'stopVM',
        'Hard Stop VM' => 'hardStopVM',
    ];
    } else if ($vmStatus == 'stopped') {
    $buttons = [
        'Start VM' => 'startVM',
    ];
    }

    return $buttons;
}

function fibacloud_ClientAreaCustomButtonArray($params) {
    $vmStatus = fibacloud_GetVmStatus($params);
    $buttons = [];
    if ($vmStatus == 'running') {
    $buttons = [
        'Reset VM' => 'restartVM',
        'Reboot VM' => 'rebootVM',
        'Shutdown VM' => 'stopVM',
        'Hard Stop VM' => 'hardStopVM',
    ];
    } else if ($vmStatus == 'stopped') {
    $buttons = [
        'Start VM' => 'startVM',
    ];
    }

    return $buttons;
}

function fibacloud_GetStatus(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];
        
        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId";
        
        $response = callAPI('GET', $apiUrl, $params['serverusername'], $params['serverpassword']);
        
        if (isset($response['vm']) && isset($response['vm']['status'])) {
            switch ($response['vm']['status']) {
                case 'running':
                    return 'online';
                case 'stopped':
                    return 'offline';
                default:
                    return 'unknown';
            }
        } else {
            return 'API response does not include VM status.';
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_ClientArea(array $params) {
    $smarty = new Smarty();
    $requestedAction = isset($_REQUEST['customAction']) ? $_REQUEST['customAction'] : '';
    $modulelink = $params['modulelink'];
    $serviceid = $params['serviceid'];
    
    if ($requestedAction == 'addNetworkInterface') {
        $addInterfaceResult = fibacloud_AddNetworkInterface($params);
        $redirectUrl = 'clientarea.php?action=productdetails&id=' . $serviceid;
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    if ($requestedAction == 'deleteNetworkInterface') {
        $interfaceId = $_POST['interfaceId'];
        $deleteParams = $params;
        $deleteParams['interfaceId'] = $interfaceId;
        $deleteInterfaceResult = fibacloud_DeleteNetworkInterface($deleteParams);
        $redirectUrl = 'clientarea.php?action=productdetails&id=' . $serviceid;
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    if ($requestedAction == 'clientRebuildVM' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $templateId = $_POST['templateId'];
        $rebuildResult = fibacloud_ClientAreaRebuildVM($params + ['templateId' => $templateId]);
        $redirectUrl = 'clientarea.php?action=productdetails&id=' . $serviceid;
        header('Location: ' . $redirectUrl);
        exit;
    }
    
   if ($requestedAction == 'updateRdns' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $ipaddresses = $_POST['ipaddress'];
        $ptrcontents = $_POST['ptrcontent'];

        if (count($ipaddresses) != count($ptrcontents)) {
           throw new Exception("IP addresses and PTR contents count mismatch");
        }

        $rdnsUpdates = [];
        for ($i = 0; $i < count($ipaddresses); $i++) {
           $rdnsUpdates[$ipaddresses[$i]] = $ptrcontents[$i];
        }
        $updateResult = fibacloud_UpdateRdns($params, $rdnsUpdates);
        $smarty->assign('updateResult', $updateResult);
        $redirectUrl = 'clientarea.php?action=productdetails&id=' . $serviceid;
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    try {
        $vmDetails = fibacloud_GetVmDetails($params);
        $getRdns = fibacloud_GetRdns($params);

        $smarty = new Smarty();
        $modulePath = __DIR__;
        $smarty->setTemplateDir($modulePath . '/templates');
        $smarty->assign('resultMessage', $resultMessage);
        $smarty->assign('vmDetails', $vmDetails);
        $smarty->assign('getRdns', $getRdns);
        $smarty->assign('serviceid', $serviceid);
        
        if (is_array($vmDetails) && isset($vmDetails['template_name'])) { if (strpos($vmDetails['template_name'], 'Windows') === 0) { $usernameDisplay = 'Administrator'; } elseif (isset($vmDetails['username'])) { $usernameDisplay = $vmDetails['username']; } else {$usernameDisplay = 'root';}} else {echo "Eroor";}

        $smarty->assign('usernameDisplay', $usernameDisplay);

        if ($requestedAction == 'rebuild') {
            $serviceId = isset($_POST['id']) ? $_POST['id'] : '';
            $templates = fibacloud_GetTemplates($params);
            $smarty->assign('templates', $templates);
            $smarty->assign('rebuildResult', $rebuildResult);
            $templateFile = 'rebuild.tpl';
        } elseif ($requestedAction == 'interfaces') {
            $serviceId = isset($_POST['id']) ? $_POST['id'] : '';
            $smarty->assign('serviceId', $serviceId);
            $templateFile = 'interfaces.tpl';
        } elseif ($requestedAction == 'storage') {
            $serviceId = isset($_POST['id']) ? $_POST['id'] : '';
            $smarty->assign('serviceId', $serviceId);
            $templateFile = 'storage.tpl';
        } elseif ($requestedAction == 'ips') {
            $serviceId = isset($_POST['id']) ? $_POST['id'] : '';
            $smarty->assign('serviceId', $serviceId);
            $templateFile = 'ips.tpl';
        } else {
            $templateFile = 'overview.tpl';
        }

        $output = $smarty->fetch($templateFile);
        return $output;
    } catch (Exception $e) {
        return '<p>Error: ' . $e->getMessage() . '</p>';
    }
}

function fibacloud_GetVmDetails($params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId";

        $response = callAPI('GET', $apiUrl, $params['serverusername'], $params['serverpassword']);

        if (!empty($response) && isset($response['vm'])) {
            return $response['vm'];
        } else {
            throw new Exception("VM details not found in the API response.");
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_ClientAreaRebuildVM($params) {
    if (isset($_POST['templateId'])) {
        $templateId = $_POST['templateId'];
        $serviceId = $params['serviceid'];
        
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];
        
        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/rebuild";
        $username = $params['serverusername'];
        $password = $params['serverpassword'];

        try {
            $response = callAPI('POST', $apiUrl, $username, $password, ['template' => $templateId]);

            if (isset($response['status']) && $response['status']) {
                return ['success' => "VM rebuild initiated successfully."];
            } else {
                $errorMessage = isset($response['error']) ? $response['error'] : "Unknown error";
                return ['error' => "Rebuild Error: " . $errorMessage];
            }
        } catch (Exception $e) {
            return ['error' => "API Call Failed: " . $e->getMessage()];
        }
    }
}

function fibacloud_GetTemplates($params) {
    $serviceId = $params['serviceid'];
    $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

    if ($orderAndVmId === null) {
        throw new Exception("Failed to retrieve orderId and vmId from service notes.");
    }

    $orderId = $orderAndVmId['orderId'];
    $vmId = $orderAndVmId['vmId'];
    $username = $params['serverusername'];
    $password = $params['serverpassword'];

    $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/templates";

    try {
        $response = callAPI('GET', $apiUrl, $username, $password);

        if (isset($response['templates'])) {
            return $response['templates'];
        } else {
            throw new Exception("Templates not found in the response.");
        }
    } catch (Exception $e) {
        return ["error" => "API Call Failed: " . $e->getMessage()];
    }
}

function fibacloud_GetVmStatus($params) {
    $serviceId = $params['serviceid'];
    $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

    if ($orderAndVmId === null) {
        throw new Exception("Failed to retrieve orderId and vmId from service notes.");
    }

    $orderId = $orderAndVmId['orderId'];
    $vmId = $orderAndVmId['vmId'];
    $username = $params['serverusername'];
    $password = $params['serverpassword'];

    $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId";

    try {
        $response = callAPI('GET', $apiUrl, $username, $password);

        if (isset($response['vm']) && isset($response['vm']['status'])) {
            return $response['vm']['status'];
        } else {
            throw new Exception("VM status not found in the response.");
        }
    } catch (Exception $e) {
        return "API Call Failed: " . $e->getMessage();
    }
}

function fibacloud_StartVM($params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/start";

        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword']);
        sleep(5);
        
        if (isset($response['status']) && $response['status'] === true) {
            return 'success';
        } else {
            return 'An error occurred during VM start: ' . ($response['message'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_StopVM($params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/shutdown";

        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword']);
        sleep(5);
        
        if (isset($response['status']) && $response['status'] === true) {
            return 'success';
        } else {
            return 'An error occurred during VM shutdown: ' . ($response['message'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_HardStopVM(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/stop";

        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword']);
        sleep(5);
        
        if (isset($response['status']) && $response['status'] === true) {
            return 'success';
        } else {
            return 'An error occurred during VM hard stop: ' . ($response['message'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_RestartVM(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/reset";

        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword']);
        sleep(5);
        
        if (isset($response['status']) && $response['status'] === true) {
            return 'success';
        } else {
            return 'An error occurred during VM restart: ' . ($response['message'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_RebootVM(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/reboot";

        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword']);
        sleep(5);
        
        if (isset($response['status']) && $response['status'] === true) {
            return 'success';
        } else {
            return 'An error occurred during VM reboot: ' . ($response['message'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        return 'API Call Failed: ' . $e->getMessage();
    }
}

function fibacloud_AddNetworkInterface(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/interfaces";

        $response = callAPI('POST', $apiUrl, $params['serverusername'], $params['serverpassword'], []);
        sleep(5);
        
        if (!isset($response['status']) || $response['status'] !== true) {
            throw new Exception('Failed to add network interface: ' . ($response['message'] ?? 'Unknown error'));
        }
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall(
            'fibacloud',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}

function fibacloud_DeleteNetworkInterface(array $params) {
    try {
        $serviceId = $params['serviceid'];
        $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($serviceId);

        if ($orderAndVmId === null) {
            throw new Exception("Failed to retrieve orderId and vmId from service notes.");
        }

        $orderId = $orderAndVmId['orderId'];
        $vmId = $orderAndVmId['vmId'];
        $interfaceId = $params['interfaceId'];

        $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/vms/$vmId/interfaces/net$interfaceId";

        $response = callAPI('DELETE', $apiUrl, $params['serverusername'], $params['serverpassword'], []);
        sleep(5);
        
        if (!isset($response['status']) || $response['status'] !== true) {
            throw new Exception('Failed to delete network interface: ' . ($response['message'] ?? 'Unknown error'));
        }
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall(
            'fibacloud',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}

function fibacloud_GetRdns(array $params) {
    $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($params['serviceid']);
    if ($orderAndVmId === null) {
        throw new Exception("Order ID and VM ID could not be retrieved.");
    }

    $orderId = $orderAndVmId['orderId'];
    $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/rdns";

    $response = callAPI('GET', $apiUrl, $params['serverusername'], $params['serverpassword']);
    return $response;
}

function fibacloud_UpdateRdns($params, $rdnsUpdates) {
    $orderAndVmId = fibacloud_getOrderAndVmIdFromNotes($params['serviceid']);
    if ($orderAndVmId === null) {
        throw new Exception("Order ID and VM ID could not be retrieved.");
    }

    $orderId = $orderAndVmId['orderId'];
    $apiUrl = "https://cloud.fibacloud.com/api/service/$orderId/rdns";

    $postData = [];
    foreach ($rdnsUpdates as $ip => $ptr) {
        $postData[$ip] = $ptr;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($params['serverusername'] . ':' . $params['serverpassword']),
        'Content-Type: multipart/form-data'
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        throw new Exception("cURL Error: " . curl_error($ch));
    }

    curl_close($ch);
    $decodedResponse = json_decode($response, true);
    if (!$decodedResponse || !isset($decodedResponse['info']) || !in_array("revdnsupdated", $decodedResponse['info'])) {
        throw new Exception("RDNS update failed or the response format is unexpected.");
    }

    return "RDNS updated successfully.";
}
