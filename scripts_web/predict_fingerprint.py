import sys
import numpy as np
import tensorflow as tf
from tensorflow.keras.preprocessing import image

# Label mappings
labels = {0: "Rayan", 1: "Amine", 2: "Bilal"}

# Image size
IMG_SIZE = (128, 128)

def predict_fingerprint(image_path, threshold=0.99):
    model = tf.keras.models.load_model('fingerprint_model.h5')

    img = image.load_img(image_path, target_size=IMG_SIZE)
    img_array = image.img_to_array(img) / 255.0
    img_array = np.expand_dims(img_array, axis=0)

    predictions = model.predict(img_array)
    predicted_label = np.argmax(predictions)
    confidence = np.max(predictions)

    print(f"Predicted vector: {predictions}, confidence: {confidence:.2f}")

    if confidence >= threshold:
        print(f"Predicted person: {labels[predicted_label]} (confidence: {confidence:.2f})")
    else:
        print("Predicted person: Unknown (low confidence)")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python3 predict_fingerprint.py <image_path>")
        sys.exit(1)

    image_path = sys.argv[1]
    predict_fingerprint(image_path)