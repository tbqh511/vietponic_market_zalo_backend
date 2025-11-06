# CORS Configuration for Production Deployment

## Problem
When deploying to production, you may encounter CORS errors like:
```
Access to fetch at 'http://localhost:2999/' (redirected from 'https://yourdomain.com/api/orders') from origin 'http://localhost:2999' has been blocked by CORS policy: No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

## Solution

### 1. Update .env file
Make sure your `.env` file has the correct APP_URL:
```env
APP_URL=https://yourdomain.com
```

### 2. CORS Middleware
The `CorsMiddleware` has been added to handle CORS properly for all API requests.

### 3. If still having issues:
- Clear Laravel cache: `php artisan cache:clear`
- Clear config cache: `php artisan config:clear`
- Clear route cache: `php artisan route:clear`

### 4. Server Configuration
Make sure your web server (Apache/Nginx) allows CORS headers to be passed through.

### 5. HTTPS Configuration
Ensure your server is properly configured for HTTPS and that all redirects are handled correctly.

## Testing
After deployment, test API endpoints using tools like Postman or curl to verify CORS headers are present:
```bash
curl -H "Origin: https://yourfrontenddomain.com" -v https://yourdomain.com/api/orders
```