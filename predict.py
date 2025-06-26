from flask import Flask, request, jsonify
from prophet import Prophet
import pandas as pd
from datetime import datetime
import logging
import traceback

app = Flask(__name__)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/predict', methods=['POST'])
def predict():
    try:
        logger.info("Received prediction request")
        if not request.is_json:
            logger.error("Request is not JSON")
            return jsonify({'error': 'Request must be JSON'}), 400
            
        data = request.json.get('data')
        if not data:
            logger.error("No data field in request")
            return jsonify({'error': 'No data field in request'}), 400
            
        logger.info(f"Received data: {data}")
        
        # Convert to DataFrame
        df = pd.DataFrame(data)
        df['ds'] = pd.to_datetime(df['ds'])
        
        # Configure Prophet model with church-specific settings
        model = Prophet(
            yearly_seasonality=True,  # Capture yearly patterns (e.g., Christmas, Easter)
            weekly_seasonality=True,  # Capture weekly patterns (Sunday services)
            daily_seasonality=False,  # Not needed for monthly data
            seasonality_mode='multiplicative',  # Better for financial data
            changepoint_prior_scale=0.05,  # More conservative trend changes
            seasonality_prior_scale=10.0,  # Stronger seasonality
            holidays_prior_scale=10.0  # Stronger holiday effects
        )
        
        # Add custom seasonality for church donations
        model.add_seasonality(
            name='monthly',
            period=30.5,
            fourier_order=5
        )
        
        # Add custom seasonality for quarterly patterns
        model.add_seasonality(
            name='quarterly',
            period=91.25,
            fourier_order=3
        )
        
        # Add custom holidays for church events
        church_holidays = pd.DataFrame([
            {'holiday': 'Christmas', 'ds': '2024-12-25'},
            {'holiday': 'Easter', 'ds': '2024-04-01'},
            {'holiday': 'Thanksgiving', 'ds': '2024-11-28'},
            {'holiday': 'New Year', 'ds': '2024-01-01'}
        ])
        model.add_holidays(church_holidays)
        
        # Fit the model
        model.fit(df)
        
        # Make future predictions
        future = model.make_future_dataframe(periods=12, freq='M')
        forecast = model.predict(future)
        
        # Get all predictions for 2025
        forecast_data = forecast[forecast['ds'].dt.year == 2025][['ds', 'yhat', 'yhat_lower', 'yhat_upper']]
        
        # Convert to dictionary format
        result = []
        for _, row in forecast_data.iterrows():
            result.append({
                'ds': row['ds'].strftime('%Y-%m-%d'),
                'yhat': float(row['yhat']),
                'yhat_lower': float(row['yhat_lower']),
                'yhat_upper': float(row['yhat_upper'])
            })
        
        logger.info(f"Prediction result: {result}")
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Error in prediction: {str(e)}")
        logger.error(f"Traceback: {traceback.format_exc()}")
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000) 