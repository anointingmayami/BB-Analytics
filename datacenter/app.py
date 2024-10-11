import json
import logging
from flask import Flask, jsonify, request
from flask_restful import Resource, Api
import os

# Initialize the Flask app and API
app = Flask(__name__)
api = Api(app)

# Define where to store the encrypted data (analytics data warehouse)
DATA_FILE = 'analytics_data.json'

# Set up logging
logging.basicConfig(filename='app.log', level=logging.DEBUG,
                    format='%(asctime)s %(levelname)s %(name)s %(message)s')
logger = logging.getLogger(__name__)

def load_data():
    try:
        # Load the actual data from a JSON file
        if os.path.exists(DATA_FILE):
            with open(DATA_FILE, 'r') as file:
                data = json.load(file)
                return data
        else:
            raise FileNotFoundError(f"{DATA_FILE} not found.")
        
    except FileNotFoundError as e:
        logger.error(f"File error: {str(e)}")
        return None  # Return None when data is not available
    
    except json.JSONDecodeError as e:
        logger.error(f"JSON decode error: {str(e)}")
        return None  # Return None if there is an issue parsing the file

    except Exception as e:
        logger.error(f"An error occurred: {str(e)}")
        return None  # Handle general errors

# Endpoint to retrieve the top links
@app.route('/analytics/top-links', methods=['GET'])
def top_links():
    try:
        data = load_data()
        if data is None:
            logger.error("No data available.")
            return jsonify({'error': 'No data available.'}), 404

        top_links = data.get('top_links', 'No top links found')
        return jsonify(top_links), 200
    
    except Exception as e:
        logger.error(f"Error retrieving top links: {str(e)}")
        return jsonify({'error': f"Failed to retrieve top links. {str(e)}"}), 500

# Endpoint to sync data
@app.route('/analytics/sync', methods=['POST'])  # Changed from GET to POST for syncing data
def sync():
    try:
        # Ensure data is sent in the request as JSON
        if not request.is_json:
            logger.warning("Invalid request: No JSON data received.")
            return jsonify({'error': 'Request must be JSON'}), 400
            
        # Receive JSON payload from the request
        new_data = request.json
        current_data = load_data() or {}  # Load current data, default to empty dict if None
            
        # Validate data contains the expected 'top_links' key
        if 'top_links' not in new_data:
            logger.warning("Invalid data structure: Missing 'top_links'.")
            return jsonify({'error': "'top_links' field is missing from the request"}), 400

        # Update the current data with new data
        current_data['top_links'] = new_data['top_links']

        # Write the updated data back to the file
        with open(DATA_FILE, 'w') as file:
            json.dump(current_data, file)

        logger.info("Data synced successfully.")
        return jsonify({'message': 'Data synced successfully.'}), 200

    except Exception as e:
        logger.error(f"Error syncing data: {str(e)}")
        return jsonify({'error': f"Failed to sync data. {str(e)}"}), 500

if __name__ == '__main__':
    app.run(debug=True)
