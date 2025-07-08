from flask import Flask, request, jsonify
from prophet import Prophet
import pandas as pd
from datetime import datetime, timedelta
import logging
import traceback
import numpy as np

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
            
        logger.info(f"Received data points: {len(data)}")
        
        # Convert to DataFrame
        df = pd.DataFrame(data)
        df['ds'] = pd.to_datetime(df['ds'])
        
        # Sort by date to ensure proper order
        df = df.sort_values('ds')
        
        # Handle missing or invalid values
        df = df.dropna()
        df = df[df['y'] >= 0]  # Remove negative values
        
        if len(df) < 3:
            logger.error("Insufficient data points for prediction")
            return jsonify({'error': 'Need at least 3 data points for prediction'}), 400
        
        # Configure Prophet model with church-specific settings
        model = Prophet(
            yearly_seasonality=True,  # Capture yearly patterns (Christmas, Easter, etc.)
            weekly_seasonality=False,  # Not needed for monthly data
            daily_seasonality=False,   # Not needed for monthly data
            seasonality_mode='multiplicative',  # Better for financial data with trends
            changepoint_prior_scale=0.1,  # Allow moderate trend changes
            seasonality_prior_scale=10.0,  # Strong seasonality
            holidays_prior_scale=10.0,     # Strong holiday effects
            interval_width=0.8             # 80% confidence interval
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
        
        # Fit the model
        logger.info("Fitting Prophet model...")
        model.fit(df)
        
<<<<<<< HEAD
        # Make future predictions specifically for 2025
        # Create future dataframe for all months of 2025
        future_dates = pd.date_range(start='2025-01-01', end='2025-12-31', freq='MS')
        future = pd.DataFrame({'ds': future_dates})
        
        # Make predictions
        forecast = model.predict(future)
        
        # Get only the predictions for 2025
        predictions_2025 = forecast[forecast['ds'].dt.year == 2025][['ds', 'yhat', 'yhat_lower', 'yhat_upper']]
=======
        # Make future predictions
        future = model.make_future_dataframe(periods=12, freq='M')
        forecast = model.predict(future)
        
        # Get all predictions for 2025
        forecast_data = forecast[forecast['ds'].dt.year == 2025][['ds', 'yhat', 'yhat_lower', 'yhat_upper']]
>>>>>>> e72896b2a2e757c3b179363c20ce46759e263081
        
        # Convert to dictionary format
        result = []
        for _, row in predictions_2025.iterrows():
            result.append({
                'ds': row['ds'].strftime('%Y-%m-%d'),
                'yhat': max(0, float(row['yhat'])),  # Ensure non-negative predictions
                'yhat_lower': max(0, float(row['yhat_lower'])),
                'yhat_upper': max(0, float(row['yhat_upper']))
            })
        
        logger.info(f"Generated {len(result)} predictions for 2025")
        logger.info(f"Prediction range: {min([r['yhat'] for r in result])} - {max([r['yhat'] for r in result])}")
        
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Error in prediction: {str(e)}")
        logger.error(f"Traceback: {traceback.format_exc()}")
        return jsonify({'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({'status': 'healthy', 'timestamp': datetime.now().isoformat()})

@app.route('/predictions/summary', methods=['POST'])
def get_prediction_summary():
    """Get a summary of predictions with additional statistics"""
    try:
        if not request.is_json:
            return jsonify({'error': 'Request must be JSON'}), 400
            
        data = request.json.get('data')
        if not data:
            return jsonify({'error': 'No data field in request'}), 400
        
        # Get basic predictions
        predictions_response = predict()
        if predictions_response.status_code != 200:
            return predictions_response
        
        predictions = predictions_response.json
        
        if not predictions:
            return jsonify({'error': 'No predictions generated'}), 400
        
        # Calculate summary statistics
        predicted_values = [p['yhat'] for p in predictions]
        total_predicted = sum(predicted_values)
        avg_monthly = total_predicted / len(predicted_values)
        
        # Calculate growth rate (if we have historical data)
        if len(data) >= 2:
            recent_data = sorted(data, key=lambda x: x['ds'])[-6:]  # Last 6 months
            if len(recent_data) >= 2:
                recent_avg = sum(d['y'] for d in recent_data) / len(recent_data)
                growth_rate = ((avg_monthly - recent_avg) / recent_avg * 100) if recent_avg > 0 else 0
            else:
                growth_rate = 0
        else:
            growth_rate = 0
        
        # Find best and worst months
        best_month = max(predictions, key=lambda x: x['yhat'])
        worst_month = min(predictions, key=lambda x: x['yhat'])
        
        summary = {
            'predictions': predictions,
            'summary_stats': {
                'total_predicted_income': total_predicted,
                'average_monthly_income': avg_monthly,
                'predicted_growth_rate': growth_rate,
                'best_month': {
                    'date': best_month['ds'],
                    'amount': best_month['yhat']
                },
                'worst_month': {
                    'date': worst_month['ds'],
                    'amount': worst_month['yhat']
                },
                'prediction_confidence': {
                    'lower_bound': sum(p['yhat_lower'] for p in predictions),
                    'upper_bound': sum(p['yhat_upper'] for p in predictions)
                }
            }
        }
        
        return jsonify(summary)
        
    except Exception as e:
        logger.error(f"Error in prediction summary: {str(e)}")
        logger.error(f"Traceback: {traceback.format_exc()}")
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True) 