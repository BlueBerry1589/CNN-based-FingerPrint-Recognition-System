import os
import cv2
from pyfingerprint.pyfingerprint import PyFingerprint

# Constants
IMG_SIZE = (128, 128)  # Define the image size here

# Initialize fingerprint sensor
try:
    f = PyFingerprint('/dev/ttyAMA0', 57600, 0xFFFFFFFF, 0x00)
    if not f.verifyPassword():
        raise Exception("Sensor password mismatch!")
except Exception as e:
    print("Sensor initialization failed:", e)
    exit(1)

# Output paths
output_png_path = os.path.join(os.getcwd(), "captured_fingerprint.png")
temp_bmp_path = os.path.join(os.getcwd(), "temp_fingerprint.bmp")

print("Waiting for finger...")
while not f.readImage():
    pass

try:
    f.downloadImage(temp_bmp_path)
    img = cv2.imread(temp_bmp_path)
    
    if img is None:
        print("Failed to read downloaded image.")
        exit(1)

    # Resize and ensure 3-channel RGB format
    img = cv2.resize(img, IMG_SIZE)
    img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)  # Convert from BGR to RGB
    
    cv2.imwrite(output_png_path, img_rgb)
    print(f"Fingerprint image saved to: {output_png_path}")

finally:
    if os.path.exists(temp_bmp_path):
        os.remove(temp_bmp_path)