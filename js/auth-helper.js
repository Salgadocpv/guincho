/**
 * Authentication Helper
 * Handles authentication for testing and development
 */

class AuthHelper {
    constructor() {
        this.authToken = localStorage.getItem('auth_token');
        this.userData = localStorage.getItem('userData');
        this.tokenTimestamp = localStorage.getItem('token_timestamp');
    }

    /**
     * Get the correct API path based on current location
     */
    getApiPath() {
        const currentPath = window.location.pathname;
        if (currentPath.includes('/client/')) {
            return '../api';
        } else if (currentPath.includes('/driver/')) {
            return '../api';
        } else {
            return 'api';
        }
    }

    /**
     * Get or create authentication token for testing
     */
    async getAuthToken() {
        // For client pages, try to login first to get the correct user ID, then use a simple token
        if (window.location.pathname.includes('/client/') || window.location.pathname.includes('test-cancel.html')) {
            // Check if we have valid stored data
            const storedToken = localStorage.getItem('auth_token');
            const storedUserData = localStorage.getItem('userData');
            
            if (storedToken && storedUserData && !storedToken.includes('test_client_2_')) {
                // We have a real session token from login, use it
                return storedToken;
            }
            
            // No valid session, try to login to get user ID
            try {
                const loginResponse = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: 'cliente@iguincho.com',
                        password: 'teste123'
                    })
                });
                
                const loginData = await loginResponse.json();
                
                if (loginData.success && loginData.data) {
                    // Store the session data
                    localStorage.setItem('auth_token', loginData.data.session_token);
                    localStorage.setItem('userData', JSON.stringify(loginData.data));
                    return loginData.data.session_token;
                }
            } catch (error) {
                console.log('Login failed, using fallback token');
            }
            
            // Fallback: use any test token that will trigger authenticateTestUser
            return 'test_client_fallback';
        }
        
        // For other pages, use the original logic
        if (this.authToken && this.authToken !== 'null' && this.authToken !== '') {
            return this.authToken;
        }

        // Try to get test token
        return this.createTestSession();
    }

    /**
     * Create a test session for development
     */
    async createTestSession() {
        try {
            // Determine the correct API path based on current location
            const apiPath = this.getApiPath();
            
            // Try to login with test credentials
            const response = await fetch(`${apiPath}/auth/login.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: 'cliente@iguincho.com',
                    password: 'teste123'
                })
            });

            const data = await response.json();
            
            if (data.success) {
                // Store token and user data
                const currentTimestamp = Math.floor(Date.now() / 1000);
                localStorage.setItem('auth_token', data.data.session_token);
                localStorage.setItem('userData', JSON.stringify(data.data));
                localStorage.setItem('token_timestamp', currentTimestamp.toString());
                
                this.authToken = data.data.session_token;
                this.userData = JSON.stringify(data.data);
                this.tokenTimestamp = currentTimestamp.toString();
                
                return data.data.session_token;
            } else {
                // If login fails, use test token
                return this.useTestToken();
            }
        } catch (error) {
            console.log('Login failed, using test mode:', error.message);
            return this.useTestToken();
        }
    }

    /**
     * Use test token for development
     */
    useTestToken() {
        // Generate a valid test client token with current timestamp
        const currentTimestamp = Math.floor(Date.now() / 1000);
        const testToken = `test_client_2_${currentTimestamp}`;
        
        // Create mock user data
        const testUserData = {
            user: {
                id: 2,
                user_type: 'client',
                full_name: 'Cliente Teste',
                email: 'cliente@iguincho.com',
                phone: '(11) 99999-9999'
            },
            session_token: testToken,
            expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString() // 24 hours
        };
        
        const currentTimestamp = Math.floor(Date.now() / 1000);
        localStorage.setItem('auth_token', testToken);
        localStorage.setItem('userData', JSON.stringify(testUserData));
        localStorage.setItem('token_timestamp', currentTimestamp.toString());
        
        this.authToken = testToken;
        this.userData = JSON.stringify(testUserData);
        this.tokenTimestamp = currentTimestamp.toString();
        
        return testToken;
    }

    /**
     * Get user data
     */
    getUserData() {
        try {
            return this.userData ? JSON.parse(this.userData) : null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!(this.authToken && this.userData);
    }

    /**
     * Logout user
     */
    logout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('userData');
        localStorage.removeItem('token_timestamp');
        this.authToken = null;
        this.userData = null;
        this.tokenTimestamp = null;
    }

    /**
     * Make authenticated API request
     */
    async apiRequest(url, options = {}) {
        const token = await this.getAuthToken();
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        };

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };

        try {
            const response = await fetch(url, mergedOptions);
            
            // Check if request was successful
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // If auth failed, try to refresh token
            if (!data.success && data.message && data.message.includes('Token')) {
                console.log('Token expired, refreshing...');
                this.logout();
                const newToken = await this.getAuthToken();
                
                // Retry request with new token
                mergedOptions.headers.Authorization = `Bearer ${newToken}`;
                const retryResponse = await fetch(url, mergedOptions);
                return await retryResponse.json();
            }
            
            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    /**
     * Create test driver user for testing driver features
     */
    async createTestDriver() {
        try {
            // First create the user
            const userResponse = await this.apiRequest('api/auth/register-client.php', {
                method: 'POST',
                body: JSON.stringify({
                    full_name: 'Guincheiro Teste',
                    cpf: '987.654.321-00',
                    birth_date: '1985-05-15',
                    phone: '(11) 88888-8888',
                    whatsapp: '(11) 88888-8888',
                    email: 'guincheiro@teste.com',
                    password: 'teste123',
                    password_confirm: 'teste123',
                    terms_accepted: true,
                    marketing_accepted: false
                })
            });

            if (userResponse.success) {
                // Update user type to driver
                // This would normally be done through a proper driver registration process
                console.log('Test driver user created');
            }

            return userResponse.success;
        } catch (error) {
            console.error('Error creating test driver:', error);
            return false;
        }
    }

    /**
     * Switch to driver mode for testing
     */
    async switchToDriverMode() {
        const testDriverData = {
            user: {
                id: 2,
                user_type: 'driver',
                full_name: 'Guincheiro Teste',
                email: 'guincheiro@iguincho.com',
                phone: '(11) 88888-8888'
            },
            session_token: 'driver_test_token_' + Date.now(),
            expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString()
        };
        
        localStorage.setItem('auth_token', testDriverData.session_token);
        localStorage.setItem('userData', JSON.stringify(testDriverData));
        
        this.authToken = testDriverData.session_token;
        this.userData = JSON.stringify(testDriverData);
        
        return testDriverData.session_token;
    }

    /**
     * Switch to client mode for testing
     */
    async switchToClientMode() {
        return this.useTestToken();
    }
}

// Global auth helper instance
window.authHelper = new AuthHelper();

// Auto-initialize auth token when page loads
document.addEventListener('DOMContentLoaded', async function() {
    try {
        await window.authHelper.getAuthToken();
        console.log('Auth helper initialized');
    } catch (error) {
        console.error('Auth helper initialization failed:', error);
    }
});