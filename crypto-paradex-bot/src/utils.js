import { shortString } from 'starknet';
import axios from 'axios';

export class ParadexUtils {
    static API_BASE_URL = 'https://api.testnet.paradex.trade/v1';

    static async getSystemConfig() {
        console.log("🔍 Getting system configuration...");
        const configResponse = await axios.get(`${this.API_BASE_URL}/system/config`);
        return {
            chainId: shortString.encodeShortString(configResponse.data.starknet_chain_id)
        };
    }

    static createOnboardingHeaders(ethAddress, paradexAddress, signature, timestamp) {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'PARADEX-ETHEREUM-ACCOUNT': ethAddress,
            'PARADEX-STARKNET-ACCOUNT': paradexAddress,
            'PARADEX-STARKNET-SIGNATURE': signature,
            'PARADEX-TIMESTAMP': timestamp.toString()
        };
    }

    static createAuthHeaders(paradexAddress, signature, timestamp, expiration) {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'PARADEX-STARKNET-ACCOUNT': paradexAddress,
            'PARADEX-STARKNET-SIGNATURE': signature,
            'PARADEX-TIMESTAMP': timestamp.toString(),
            'PARADEX-SIGNATURE-EXPIRATION': expiration.toString()
        };
    }

    static handleError(error, context) {
        console.error(`💥 Error in ${context}:`);
        
        if (error.response) {
            console.error("📊 Status:", error.response.status);
            console.error("📄 Error:", error.response.data);
            
            if (error.response.status === 409 || 
                (error.response.data?.error && 
                 error.response.data.error.includes("already"))) {
                console.log("ℹ️ Account already registered, continuing...");
                return { success: true, isAlreadyRegistered: true };
            }
        } else {
            console.error("📚 Error details:", error.message);
        }
        
        return { success: false, error: error.message };
    }
}
