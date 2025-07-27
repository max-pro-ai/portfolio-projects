# Paradex Trading Bot

## Overview
A sophisticated cryptocurrency trading bot designed to interact with the Paradex platform using StarkNet authentication. This bot implements secure key management and automated authentication flow for cryptocurrency trading operations.

## Features
- StarkNet authentication implementation
- Secure private key management
- Automated onboarding process
- JWT token handling
- Real-time signature generation for transactions
- Error handling and retry mechanisms

## Technologies Used
- Node.js
- StarkNet SDK
- Axios for HTTP requests
- Cryptographic functions for secure signatures

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/crypto-paradex-bot.git
cd crypto-paradex-bot
```

2. Install dependencies:
```bash
npm install
```

3. Configure your environment variables:
   - Create a `.env` file in the root directory
   - Add your credentials:
```env
ETH_ADDRESS=your_ethereum_address
PARADEX_PRIVATE_KEY=your_private_key
PARADEX_ADDRESS=your_paradex_address
```

## Usage

```bash
# Run the bot
npm start
```

## Security Notes
- Never share your private keys
- Store sensitive data in environment variables
- Use secure connections for API interactions
- Regularly update dependencies

## Project Structure
```
src/
├── index.js      # Main entry point
├── auth.js       # Authentication logic
└── utils.js      # Utility functions
```

## Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

## License
MIT License - see the [LICENSE](LICENSE) file for details
