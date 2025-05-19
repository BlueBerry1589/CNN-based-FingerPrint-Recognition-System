import os
import numpy as np
import tensorflow as tf
from tensorflow.keras.preprocessing import image, image_dataset_from_directory
from tensorflow.keras.preprocessing.image import ImageDataGenerator
import matplotlib.pyplot as plt
from sklearn.model_selection import train_test_split


people = {
    0: "Rayan",
    1: "Amine",
    2: "Bilal"
}

IMG_SIZE = (128, 128)

def load_dataset(data_dir):
    images = [

    ]
    labels = []

    for person_id, name in people.items():
        folder = os.path.join(data_dir, name)
        if not os.path.isdir(folder):
            print(f"Missing folder: {folder}")
            continue

        for fname in os.listdir(folder):
            if fname.lower().endswith(('.png', '.jpg', '.jpeg')):
                path = os.path.join(folder, fname)
                try:
                    img = image.load_img(path, target_size=IMG_SIZE)
                    img_array = image.img_to_array(img)
                    images.append(img_array)
                    labels.append(person_id)
                except Exception as e:
                    print(f"Error loading {fname}: {e}")

    images = np.array(images) / 255.0
    labels = tf.keras.utils.to_categorical(labels, num_classes=len(people))
    return images, labels


dataset_dir = "./fingerprint_dataset"
images, labels = load_dataset(dataset_dir)

if len(images) == 0:
    raise Exception("No images loaded. Check dataset path.")


X_train, X_val, y_train, y_val = train_test_split(images, labels, test_size=0.2, random_state=42)


data_augmentor = tf.keras.Sequential([
    tf.keras.layers.RandomFlip("horizontal"),
    tf.keras.layers.RandomRotation(0.1),
    tf.keras.layers.RandomZoom(0.1),
    tf.keras.layers.RandomContrast(0.1)
])

# Define the regularized CNN model
model = tf.keras.Sequential([
    tf.keras.layers.InputLayer(input_shape=(IMG_SIZE[0], IMG_SIZE[1], 3)),
    data_augmentor,

    tf.keras.layers.Conv2D(32, (3, 3), activation='relu'),
    tf.keras.layers.MaxPooling2D(2, 2),

    tf.keras.layers.Conv2D(64, (3, 3), activation='relu'),
    tf.keras.layers.MaxPooling2D(2, 2),

    tf.keras.layers.Conv2D(64, (3, 3), activation='relu'),
    tf.keras.layers.MaxPooling2D(2, 2),

    tf.keras.layers.Flatten(),
    tf.keras.layers.Dropout(0.5), 
    tf.keras.layers.Dense(128, activation='relu'),
    tf.keras.layers.Dropout(0.3),
    tf.keras.layers.Dense(len(people), activation='softmax')
])

model.compile(optimizer='adam',
              loss='categorical_crossentropy',
              metrics=['accuracy'])

model.summary()

# Train
history = model.fit(X_train, y_train, epochs=80, batch_size=32, validation_data=(X_val, y_val))



model.save("fingerprint_modelver2.h5")


def predict_fingerprint(image_path, threshold=0.97):
    loaded_model = tf.keras.models.load_model("fingerprint_model.h5")

    img = image.load_img(image_path, target_size=IMG_SIZE)
    img_array = image.img_to_array(img) / 255.0
    img_array = np.expand_dims(img_array, axis=0)

    predictions = loaded_model.predict(img_array)
    predicted_label = np.argmax(predictions)
    confidence = np.max(predictions)

    print(f"Predicted vector: {predictions}, confidence: {confidence:.2f}")

    if confidence >= threshold:
        print(f"Predicted person: {people[predicted_label]} (confidence: {confidence:.2f})")
    else:
        print("Predicted person: Unknown (low confidence)")


test_dir = "./test"
for folder in os.listdir(test_dir):
    folder_path = os.path.join(test_dir, folder)
    if os.path.isdir(folder_path):
        print(f"\nTesting folder: {folder}")
        for img_name in os.listdir(folder_path):
            if img_name.lower().endswith(('.png', '.jpg', '.jpeg')):
                print(f"Predicting for {img_name}")
                predict_fingerprint(os.path.join(folder_path, img_name))


plt.figure(figsize=(12, 6))

plt.subplot(1, 2, 1)
plt.plot(history.history['accuracy'], label='Train Acc')
plt.plot(history.history['val_accuracy'], label='Val Acc')
plt.title('Accuracy')
plt.xlabel('Epochs')
plt.ylabel('Accuracy')
plt.legend()

plt.subplot(1, 2, 2)
plt.plot(history.history['loss'], label='Train Loss')
plt.plot(history.history['val_loss'], label='Val Loss')
plt.title('Loss')
plt.xlabel('Epochs')
plt.ylabel('Loss')
plt.legend()

plt.tight_layout()
plt.show()
