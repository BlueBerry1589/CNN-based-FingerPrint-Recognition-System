# Fingerprint Recognition System

A complete fingerprint recognition solution based on a Convolutional Neural Network (CNN) with the raspberry pi 5, featuring web integration(apache+php), real-time predictions, and a simple admin dashboard.
![WhatsApp Image 2025-05-18 at 17 46 00_1c9d0f77](https://github.com/user-attachments/assets/7aff70b3-50d5-4db0-8b94-fdf2919e331b)

---

## Features

### Core Capabilities

- **CNN-Based Fingerprint Recognition Model**  
  A convolutional neural network (CNN) is used to classify fingerprints with high accuracy.

- **Data Augmentation**  
  Enhances the training dataset with transformations to improve generalization and model robustness.

- **Confidence Thresholding**  
  Automatically detects and filters out unknown fingerprints based on prediction confidence scores.

- **Training Visualization**  
  Includes training plots that track accuracy and loss over epochs for easy monitoring.

---

## Web Integration

### Apache + PHP on Raspberry Pi
1. update the system
```bash
sudo apt update
sudo apt upgrade -y
```
2. Install Apache
```bash
sudo apt install apache2 -y
```
3. Start and Enable Apache

```bash
sudo systemctl start apache2
sudo systemctl enable apache2
```


Visit the following in your web browser:

```bash
http://<your-pi-ip>
```
To find your Raspberry Pi’s IP address:
```bash
hostname -I
```
You should see the default Apache welcome page.

4. Serve Your Website from /var/www/html

Remove the default page:
```bash
sudo rm /var/www/html/index.html

```
Copy your custom website files to the web root:

```bash
sudo cp -r /path/to/your/website/* /var/www/html/
```
Replace /path/to/your/website/ with the actual path to your site's files.

5. Set Correct File Permissions


```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```
6. Restart Apache (After Any Changes)
```bash
sudo systemctl restart apache2
```
7. php installation 
```bash
sudo apt install php7.3 php7.3-fpm php7.3-cgi
```
### Admin Dashboard

- **Real-Time Logs**  
  The dashboard displays recognized fingerprints along with associated names and timestamps.

- **JSON Storage**  
  Dashboard entries are stored in a JSON file for simplicity and easy parsing.

- **Export to CSV**  
  Admins can export logged predictions (name and time) as a `.csv` file for external analysis or record keeping.

---

## Setup

### Prerequisites

- Raspberry Pi 5 with Raspberry Pi OS (64-bit recommended)
- Fingerprint scanner module UART compatible ( for our project we used AS606 Fingerprint Sensor)
- Raspberry Pi with Apache and PHP installed
- Python environment for CNN model
- Trained fingerprint recognition model

### Configue teh raspberry pi

in your raspberrypi 5 make sure to do the following istructions:

1. Activate the Serial Port

Open the Raspberry Pi configuration tool:

```bash
sudo raspi-config
 ```
When the configuration menu appears, navigate to:

Interface Options > Serial Port > Enable

Then reboot the system:
```bash
sudo reboot
```
After reboot, check if the serial port /dev/serial0 is available:

```bash
ls -l /dev/serial*
```

You should see something like:
```bash
/dev/serial0 -> ttyAMA0
``` 
2. Disable Bluetooth
Edit the config file:
```bash
sudo nano /boot/firmware/config.txt
```
in the config.txt file do the following:
```bash
enable_uart=1
dtoverlay=disable-bt  # Disables Bluetooth to free UART
```
Then reboot:
```bash
sudo reboot
```

3. Create a Python Virtual Environment
 ```bash
sudo apt update
sudo apt install python3-venv
python3 -m venv myenv
source myenv/bin/activate
```
4. install the requirements
```bash
pip install -r requirements.txt
```


### Deployment



1. Place the PHP file in your Apache server's root directory (e.g., `/var/www/html/`).
2. Make sure the trained model and Python scripts are accessible and callable from PHP (e.g., using `shell_exec()`).
3. Launch the web interface from a browser pointing to your Raspberry Pi’s IP.

### Realisation
dashboard:
![WhatsApp Image 2025-05-18 at 17 46 00_e88c052d](https://github.com/user-attachments/assets/d3e11611-957c-45e7-b9a9-5d495a3aa1af)
you need to lick on after the fingerprint is read click on verify identity 
![WhatsApp Image 2025-05-18 at 17 48 15_0efebe0d](https://github.com/user-attachments/assets/c21ce824-936c-4301-b33f-7a019811dc35)
the data of the user can be changed in the users.jason file to suit you needs
![WhatsApp Image 2025-05-18 at 17 49 45_2dd74807](https://github.com/user-attachments/assets/8f370f85-6e0a-43eb-8e0c-837a24d5ac5e)


---

## File Structure (Example)
```bash
project/
├── train_dataset/
│ └──person1
│ └──person2
│ └──person3
├── test_dataset/
│ └──test1
│ └──test2
│ └──test3
├── model/
│ └── train.py
├── scripts_web/
│ └── fingerprint_model.h5
│ └── capture_fingerprint.py
│ └── predict_fingerprint.py
│ └── index.php
│ └── users.json
│ └── dashboard.json
```

