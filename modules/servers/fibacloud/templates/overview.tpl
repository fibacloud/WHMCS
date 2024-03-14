<script>
function togglePasswordVisibility() {
    var passwordField = document.getElementById("vmPassword");
    var toggleButton = document.getElementById("toggleButton");
    if (passwordField.style.display === "none") {
        passwordField.style.display = "inline";
        toggleButton.style.display = "none";
    } else {
        passwordField.style.display = "none";
        toggleButton.style.display = "inline";
    }
}
</script>
<style>
    .card-header {
        background-color: #007bff;
        color: #fff;
    }

    .list-group-item {
        border-left: 3px solid #007bff;
    }

    .vm-detail-highlight {
        background-color: #f8f9fa;
        border-left: 4px solid #007bff;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 5px;
    }

    .btn-custom-upgrade {
        background-color: #28a745;
        border-color: #28a745;
        color: #ffffff;
    }

    .btn-custom-upgrade:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }
    .status-indicator {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 10px;
    }

    .status-indicator.running {
        background-color: #28a745;
    }

    .status-indicator.stopped {
        background-color: #dc3545;
    }

    .vm-detail-highlight {
        display: flex;
        align-items: center;
    }
    
.vm-status-circle {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    margin: 20px auto;
    position: relative;
    overflow: hidden;
}
.running {
    background-color:#007bff;
}
.starting {
    background-color:#ffc107;
}
.stopped {
    background-color:#dc3545;
}
.stopping {
    background-color:#ffc107;
}
.shutdown {
    background-color:#ffc107;
}
.resetting {
    background-color:#ffc107;
}
.rebooting {
    background-color:#ffc107;
}
.rebuild {
    background-color:#ffc107;
}
.eroor {
    background-color:#dc3545;
}
.bubble {
    position: absolute;
    bottom: 100%;
    background-color: rgba(255, 255, 255, 0.6);
    border-radius: 50%;
}

@keyframes rise {
    0% { transform: scale(0); bottom: -100px; opacity: 0; }
    50% { opacity: 1; }
    100% { bottom: 250px; transform: scale(1); opacity: 0; }
}

.bubble:nth-child(1) {
    left: 20%;
    width: 40px;
    height: 40px;
    animation: rise 4s infinite ease-in;
    animation-delay: 0s;
}

.bubble:nth-child(2) {
    left: 50%;
    width: 30px;
    height: 30px;
    animation: rise 3s infinite ease-in;
    animation-delay: 1s;
}

.bubble:nth-child(3) {
    left: 70%;
    width: 20px;
    height: 20px;
    animation: rise 5s infinite ease-in;
    animation-delay: 2s;
}

.bubble:nth-child(4) {
    left: 40%;
    width: 50px;
    height: 50px;
    animation: rise 6s infinite ease-in;
    animation-delay: 3s;
}

.bubble:nth-child(5) {
    left: 80%;
    width: 25px;
    height: 25px;
    animation: rise 7s infinite ease-in;
    animation-delay: 4s;
}
.nav-tabs {
    display:none;
   }
   .tab-pane > .row:not(.module-client-area) {
        display: none;
    }
</style>

<h2 class="mb-4">VM Overview</h2>

<div class="card mb-3">
    <div class="card-header">
        General Information
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 d-flex align-items-center">
                <div class="vm-status-circle {$vmDetails.status|lower}">
                  <div class="vm-status-text">{$vmDetails.status}</div>
                  <div class="bubble"></div>
                  <div class="bubble"></div>
                  <div class="bubble"></div>
                  <div class="bubble"></div>
                  <div class="bubble"></div>
               </div>
            </div>
            <div class="col-md-6">
                <div class="vm-detail-highlight"><strong style="margin-right:10px;">Username:</strong>{$usernameDisplay}</div>
                <div class="vm-detail-highlight"><strong style="margin-right:10px;">Password:</strong> <span id="vmPassword" style="display:none;">{$vmDetails.password}</span><button id="toggleButton" onclick="togglePasswordVisibility()" class="btn btn-red btn-sm">Show Password</button></div>
                <div class="vm-detail-highlight"><strong style="margin-right:10px;">Hostname:</strong>{$vmDetails.label}</div>
                <div class="vm-detail-highlight"><strong style="margin-right:10px;">Template:</strong>{$vmDetails.template_name}</div>
                <div class="vm-detail-highlight"><strong style="margin-right:10px;">Disk:</strong> {$vmDetails.disk} GB</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
     <div class="row">
        <div class="col-md-6" style="text-align:left;">Storage Information</div>
        <div class="col-md-6" style="text-align:right;"><a href="/clientarea.php?action=productdetails&id={$serviceid}&customAction=storage" class="btn btn-default btn-block btn-sm">Manage</a></div>
     </div>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Zone</th>
                    <th>Size (GB)</th>
                    <th>Format</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$vmDetails.storage item=storage}
                    <tr>
                        <td>{$storage.name}</td>
                        <td>DataStore</td>
                        <td>{$storage.size_gb}</td>
                        <td>{$storage.format}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>

<div class="row">
  <div class="col-md-6">  
    <div class="card mb-3">
      <div class="card-header" style="text-align:left;">Bandwidth Usage</div>
      <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Used</th>
                </tr>
            </thead>
            <tbody>
                    <tr>
                        <td><strong>Data Received</strong></td>
                        <td>{math equation="x / (1024 * 1024)" x=$vmDetails.bandwidth.data_received format="%.2f"} MB</td>
                    </tr>
                    <tr>
                        <td><strong>Data Sent</strong></td>
                        <td>{math equation="x / (1024 * 1024)" x=$vmDetails.bandwidth.data_sent format="%.2f"} MB</td>
                    </tr>
            </tbody>
        </table>
    </div>
    </div>
  </div>
  <div class="col-md-6">  
    <div class="card mb-3">
      <div class="card-header">
     <div class="row">
     <div class="col-md-6" style="text-align:left;">Network Interfaces</div>
     <div class="col-md-6" style="text-align:right;"><a href="/clientarea.php?action=productdetails&id={$serviceid}&customAction=interfaces" class="btn btn-default btn-block btn-sm">Manage</a></div>
     </div>
     </div>
      <div class="card-body">
       <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Model</th>
                    <th>MAC</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$vmDetails.interfaces item=interface}
                    <tr>
                        <td>{$interface.name}</td>
                        <td>{$interface.model}</td>
                        <td>{$interface.mac}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
    </div>
  </div>
</div>

<div class="card mb-3">
    <div class="card-header">IP Addresses</div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>IP Address</th>
                    <th>Network</th>
                    <th>Gateway</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$vmDetails.ip item=ip}
                <tr>
                    <td>{$ip.type}</td>
                    <td><b>{$ip.ip}</b></td>
                    <td>{$ip.network}</td>
                    <td>{$ip.gateway}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
