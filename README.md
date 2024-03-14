# FibaCloud.com WHMCS Reseller Modules
FibaCloud.com WHMCS Reseller Modules

## Installation
- Download latest module [release](https://github.com/fibacloud/WHMCS/raw/main/FibaCloud_WHMCS_V1.zip);
- Upload archive folder contents to your WHMCS installation root directory;
- Login to WHMCS admin panel;
- Go to System Settings > Products & Services > Servers > Add New Server
- Module: **FibaCloud**
- Hostname or IP Address: **cloud.fibacloud.com**
- Username: **Cloud Account Email**
- Password: **Cloud Account Password**
- Click on the **Continue**
- Name: **Label**
- Hostname or IP Address: **cloud.fibacloud.com**
- Click on the **Save Changes**

## Packade Configuration
 - Go to General Settings > Products & Services > Products/Services > Create A New Product
 - Product Type: **Server/VPS**
 - Product Name: **Name**
 - Module: **FibaCloud**
 - Click on the **Continue**
    - Edit Product
       - go to **Module Settings**
       - Module Name: **FibaCloud**
       - Product ID: **FibaCloud Server Package That Will Match the Package You Edited**
       - Promo Code: **If you have a promotional code, you can pass the code via API.**
       - Automatic Installation (optional): **Automatically setup the product as soon as the first payment is received**
       - Click on the **Save Changes**

## OS Configuration
- Go to General Settings > Products & Services > Configurable Option Groups > Create A New Group
- Group Name: **Operating System**
- Assigned Products: **Select All**
  - Configurable Options
     - Add New Configurable Option
     - Option Name (İmportand!): **OS**
     - Option Type: **Dropdown**
       - Add Option:
         - CentOS 7
         - CentOS 8 Stream
         - CentOS 9 Stream
         - AlmaLinux 8
         - AlmaLinux 9
         - Debian 10
         - Debian 11
         - Debian 12
         - Debian 13
         - Ubuntu 18.04
         - Ubuntu 20.04
         - Ubuntu 22.04
         - Ubuntu 23.04
         - Ubuntu 23.10
         - Rocky Linux 8
         - Rocky Linux 9
         - Click on the **Save Changes**
      - Click on the **Save Changes**
