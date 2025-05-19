import os
import cv2
import time
import numpy as np
import tensorflow as tf
from tensorflow.keras.preprocessing import image
from pyfingerprint.pyfingerprint import PyFingerprint

# Constants
IMG_SIZE = (128, 128)  # Define the image size here
CAPTURE_DELAY = 2  # Delay between captures in seconds
CONFIDENCE_THRESHOLD = 0.95

# Label mappings
labels = {0: "Rayan", 1: "Amine", 2: "Bilal"}

# Output paths
output_png_path = os.path.join(os.getcwd(), "captured_fingerprint.png")
temp_bmp_path = os.path.join(os.getcwd(), "temp_fingerprint.bmp")

def initialize_sensor():
    try:
        f = PyFingerprint('/dev/ttyAMA0', 57600, 0xFFFFFFFF, 0x00)
        if not f.verifyPassword():
            raise Exception("Sensor password mismatch!")
        return f
    except Exception as e:
        print("Sensor initialization failed:", e)
        return None

def capture_fingerprint(sensor):
    try:
        print("\nWaiting for finger...")
        while not sensor.readImage():
            time.sleep(0.1)

        sensor.downloadImage(temp_bmp_path)
        img = cv2.imread(temp_bmp_path)

        if img is None:
            print("Failed to read downloaded image.")
            return False

        # Resize and ensure 3-channel RGB format
        img = cv2.resize(img, IMG_SIZE)
        img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)  # Convert from BGR to RGB

        cv2.imwrite(output_png_path, img_rgb)
        print(f"Fingerprint image saved to: {output_png_path}")
        return True

    except Exception as e:
        print(f"Error capturing fingerprint: {e}")
        return False
    finally:
        if os.path.exists(temp_bmp_path):
            os.remove(temp_bmp_path)

def predict_fingerprint(model):
    try:
        img = image.load_img(output_png_path, target_size=IMG_SIZE)
        img_array = image.img_to_array(img) / 255.0
        img_array = np.expand_dims(img_array, axis=0)

        predictions = model.predict(img_array)
        predicted_label = np.argmax(predictions)
        confidence = np.max(predictions)

        print(f"Predicted vector: {predictions}, confidence: {confidence:.2f}")

        if confidence >= CONFIDENCE_THRESHOLD:
            print(f"Predicted person: {labels[predicted_label]} (confidence: {confidence:.2f})")
            return True
        else:
            print("Predicted person: Unknown (low confidence)")
            return False

    except Exception as e:
        print(f"Error during prediction: {e}")
        return False

def main():
    print("Initializing automatic fingerprint recognition system...")
    
    # Initialize sensor
    sensor = initialize_sensor()
    if not sensor:
        print("Failed to initialize sensor. Exiting.")
        return

    # Load the model
    try:
        model = tf.keras.models.load_model('fingerprint_model.h5')
        print("Model loaded successfully")
    except Exception as e:
        print(f"Failed to load model: {e}")
        return

    print("System ready. Starting continuous monitoring...")
    
    try:
        while True:
            if capture_fingerprint(sensor):
                predict_fingerprint(model)
            time.sleep(CAPTURE_DELAY)  # Wait before next capture

    except KeyboardInterrupt:
        print("\nStopping automatic fingerprint recognition...")
    except Exception as e:
        print(f"Unexpected error: {e}")
    finally:
        print("System stopped.")

if __name__ == "__main__":
    main()