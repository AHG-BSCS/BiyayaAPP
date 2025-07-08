# Church Financial Prediction Model

This project implements an advanced prediction model for church financial income using Facebook Prophet. The model analyzes historical financial data from **tithes and offerings only** to predict future monthly income.

## Features

- **Advanced Time Series Prediction**: Uses Facebook Prophet for accurate financial forecasting
- **Database-Driven**: All data comes from the actual tithes and offerings records in the database
- **No Hardcoded Data**: Model relies entirely on real financial data from the system
- **Real-time Predictions**: Provides monthly predictions for the next 12 months
- **Confidence Intervals**: Shows prediction ranges with upper and lower bounds
- **Growth Rate Analysis**: Calculates predicted growth compared to historical data
- **Best/Worst Month Identification**: Highlights peak and low periods

## Data Sources

The model **ONLY** uses data from:
- **Tithes** - Regular tithe contributions
- **Offerings** - Sunday and special offerings

**Excluded from predictions:**
- Bank Gifts
- Specified Gifts
- Any other financial sources

## Setup Instructions

### 1. Install Python Dependencies

```bash
pip install -r requirements.txt
```

### 2. Start the Prediction Service

```bash
python predict.py
```

The service will start on `http://localhost:5000`

### 3. Test the Model

```bash
python test_prediction.py
```

**Note**: The test script uses sample data for API testing only. Real predictions use actual database data.

## API Endpoints

### POST /predict
Main prediction endpoint that takes historical financial data and returns future predictions.

**Request:**
```json
{
  "data": [
    {"ds": "2023-01-01", "y": 15000.0},
    {"ds": "2023-02-01", "y": 14500.0},
    ...
  ]
}
```

**Response:**
```json
[
  {
    "ds": "2025-01-01",
    "yhat": 16500.0,
    "yhat_lower": 14000.0,
    "yhat_upper": 19000.0
  },
  ...
]
```

### GET /health
Health check endpoint to verify the service is running.

### POST /predictions/summary
Enhanced endpoint that provides detailed prediction statistics.

## Integration with Financial Report

The prediction model is integrated into the `financialreport.php` page:

1. **Data Collection**: Automatically fetches historical tithes and offerings data from the database
2. **Data Processing**: Sends only tithes and offerings data to the Python prediction service
3. **Prediction Processing**: Prophet analyzes patterns and generates predictions for 2025
4. **Visualization**: Displays predictions in interactive charts
5. **Summary Statistics**: Shows growth rates, best/worst months, and confidence intervals

## Model Features

### Seasonality Patterns
- **Yearly**: Captures annual patterns in giving
- **Monthly**: Accounts for monthly variations in tithes and offerings
- **Quarterly**: Identifies quarterly trends
- **Natural Patterns**: Learns patterns from actual data, no hardcoded holidays

### Data Requirements
- **Minimum Data**: At least 3 months of tithes and offerings data
- **Optimal Data**: 24 months of historical data for best predictions
- **Data Quality**: Automatically handles missing or invalid data

## Error Handling

The system includes robust error handling:
- **Fallback Predictions**: If the Prophet model fails, uses statistical fallbacks based on actual data
- **Data Validation**: Ensures sufficient data points for prediction
- **Connection Timeouts**: Handles network issues gracefully
- **Logging**: Comprehensive logging for debugging

## Performance Optimization

- **Efficient Queries**: Optimized SQL queries for tithes and offerings data
- **Data Preprocessing**: Efficient data cleaning and validation
- **Memory Management**: Optimized for large datasets
- **Response Time**: Typically responds within 5-10 seconds

## Troubleshooting

### Common Issues

1. **Service Not Starting**
   - Check if port 5000 is available
   - Verify all dependencies are installed
   - Check Python version (3.7+ required)

2. **Prediction Failures**
   - Ensure at least 3 months of tithes and offerings data
   - Check data format (dates in YYYY-MM-DD format)
   - Verify amounts are positive numbers

3. **Integration Issues**
   - Check if the Flask service is running
   - Verify network connectivity
   - Check error logs in the browser console

### Logs

The prediction service logs detailed information:
- Data processing steps
- Model fitting progress
- Prediction generation
- Error details

Check the console output for debugging information.

## Data Flow

1. **Database Query**: Fetches tithes and offerings data from the last 24 months
2. **Data Processing**: Combines and aggregates monthly totals
3. **API Call**: Sends processed data to Python prediction service
4. **Model Analysis**: Prophet analyzes patterns and trends
5. **Prediction Generation**: Creates 12 monthly predictions for 2025
6. **Results Display**: Shows predictions in charts and summary statistics

## Future Enhancements

- **Real-time Updates**: Automatic model retraining with new data
- **Advanced Analytics**: More detailed financial insights
- **Mobile Support**: Mobile-optimized prediction views
- **Export Features**: PDF/Excel export of predictions

## Support

For issues or questions:
1. Check the error logs
2. Run the test script
3. Verify the service is running
4. Check data format and quality
5. Ensure sufficient tithes and offerings data exists 