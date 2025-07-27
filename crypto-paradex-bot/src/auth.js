import { hash, ec, stark, typedData, shortString } from 'starknet';

export class ParadexAuth {
    constructor(ethAddress, paradexPrivateKey, paradexAddress) {
        this.ethAddress = ethAddress;
        this.paradexPrivateKey = paradexPrivateKey;
        this.paradexAddress = paradexAddress;
        this.publicKey = this.generatePublicKey();
    }

    generatePublicKey() {
        const privateKeyClean = this.paradexPrivateKey.startsWith('0x') ? 
            this.paradexPrivateKey.slice(2) : this.paradexPrivateKey;
        const publicKey = ec.starkCurve.getStarkKey(privateKeyClean);
        const publicKeyHex = publicKey.toString(16);
        return publicKeyHex.startsWith('0x') ? publicKeyHex : `0x${publicKeyHex}`;
    }

    signOnboardingRequest(chainId) {
        console.log("🔐 Creating onboarding signature...");
        
        const domain = {
            name: "Paradex",
            chainId: chainId,
            version: "1"
        };

        const types = {
            StarkNetDomain: [
                { name: "name", type: "felt" },
                { name: "chainId", type: "felt" },
                { name: "version", type: "felt" }
            ],
            Constant: [
                { name: "action", type: "felt" }
            ]
        };

        const message = {
            action: "Onboarding"
        };

        const typedDataMessage = {
            domain,
            primaryType: "Constant",
            types,
            message
        };

        const account = {
            address: this.paradexAddress,
            privateKey: this.paradexPrivateKey.startsWith('0x') ? 
                this.paradexPrivateKey.slice(2) : this.paradexPrivateKey
        };

        const msgHash = typedData.getMessageHash(typedDataMessage, account.address);
        const { r, s } = ec.starkCurve.sign(msgHash, account.privateKey);
        
        return JSON.stringify([r.toString(), s.toString()]);
    }

    signAuthRequest(chainId, timestamp, expiration) {
        console.log("🔐 Creating authentication signature...");
        
        const domain = {
            name: "Paradex",
            chainId: chainId,
            version: "1"
        };

        const types = {
            StarkNetDomain: [
                { name: "name", type: "felt" },
                { name: "chainId", type: "felt" },
                { name: "version", type: "felt" }
            ],
            Request: [
                { name: "method", type: "felt" },
                { name: "path", type: "felt" },
                { name: "body", type: "felt" },
                { name: "timestamp", type: "felt" },
                { name: "expiration", type: "felt" }
            ]
        };

        const message = {
            method: "POST",
            path: "/v1/auth",
            body: "",
            timestamp: timestamp,
            expiration: expiration
        };

        const typedDataMessage = {
            domain,
            primaryType: "Request",
            types,
            message
        };

        const account = {
            address: this.paradexAddress,
            privateKey: this.paradexPrivateKey.startsWith('0x') ? 
                this.paradexPrivateKey.slice(2) : this.paradexPrivateKey
        };

        const msgHash = typedData.getMessageHash(typedDataMessage, account.address);
        const { r, s } = ec.starkCurve.sign(msgHash, account.privateKey);
        
        return JSON.stringify([r.toString(), s.toString()]);
    }
}
