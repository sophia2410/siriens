# app.py
from flask import Flask, jsonify
from flask_cors import CORS
from routes.candles import candles_bp

app = Flask(__name__)
CORS(app)  # CORS 허용
app.register_blueprint(candles_bp)

@app.errorhandler(404)
def not_found(e):
    return jsonify({"error": "Not found"}), 404

@app.errorhandler(500)
def internal_error(e):
    return jsonify({"error": "Internal server error"}), 500

if __name__ == '__main__':
    app.run(debug=True, port=5000)