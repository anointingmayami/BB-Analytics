import json
from flask import Flask, jsonify, request
from flask_restful import Resource, Api
from cryptography.fernet import Fernet
import os

app = Flask(__name__)
api = Api(app)

# Encryption key for encrypting and decrypting data (You may generate this securely and store it in an env variable)
encryption_key = Fernet.generate_key()
cipher = Fernet(encryption_key)

# Define where to store the encrypted data (analytics data warehouse)
DATA_FILE = 'analytics_data.json'

# Helper function to encrypt and save data to a JSON file
def store_encrypted_data(data):
    encrypted_data = cipher.encrypt(json.dumps(data).encode())
    with open(DATA_FILE, 'wb') as f:
        f.write(encrypted_data)

# Helper function to decrypt and load data from a JSON file
def load_encrypted_data():
    if not os.path.exists(DATA_FILE):
        return {}
    with open(DATA_FILE, 'rb') as f:
        encrypted_data = f.read()
    decrypted_data = cipher.decrypt(encrypted_data)
    return json.loads(decrypted_data)

# Endpoint to retrieve the top links
class TopLinks(Resource):
    def get(self):
        data = load_encrypted_data()
        return jsonify(data.get('top_links', 'No data available'))

# Endpoint for WordPress plugin to sync new analytics data
class SyncData(Resource):
    def post(self):
        # Receive JSON payload from the WordPress plugin
        new_data = request.json
        current_data = load_encrypted_data()
        
        # Update stored data (for now, replace the old data)
        current_data['top_links'] = new_data['top_links']
        
        # Encrypt and store the updated data
        store_encrypted_data(current_data)
        
        return {'message': 'Data synced successfully'}, 200

# Register endpoints with Flask API
api.add_resource(TopLinks, '/analytics/top-links')
api.add_resource(SyncData, '/analytics/sync')

if __name__ == '__main__':
    app.run(debug=True)
