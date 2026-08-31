<?php
/**
 * Supported coins, networks, and metadata.
 *
 * @package Xdwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Xdwp_Coins
 */
class Xdwp_Coins {

	/**
	 * Get all coin definitions keyed by coin ID.
	 *
	 * Coin IDs use SYMBOL or SYMBOL_NETWORK for multi-network assets.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all() {
		static $coins = null;

		if ( null !== $coins ) {
			return $coins;
		}

		$coins = array(
			// Native coins.
			'BTC'  => self::def( 'BTC', 'Bitcoin', 'bitcoin', 'bitcoin', 'native', 8, 'btc' ),
			'BCH'  => self::def( 'BCH', 'Bitcoin Cash', 'bitcoin-cash', 'bitcoin-cash', 'native', 8, 'bch' ),
			'ETH'  => self::def( 'ETH', 'Ethereum', 'ethereum', 'ethereum', 'native', 18, 'eth' ),
			'LTC'  => self::def( 'LTC', 'Litecoin', 'litecoin', 'litecoin', 'native', 8, 'ltc' ),
			'DOGE' => self::def( 'DOGE', 'Dogecoin', 'dogecoin', 'dogecoin', 'native', 8, 'doge' ),
			'DASH' => self::def( 'DASH', 'Dash', 'dash', 'dash', 'native', 8, 'dash' ),
			'ZEC'  => self::def( 'ZEC', 'Zcash', 'zcash', 'zcash', 'native', 8, 'zec' ),
			// eCash rebased 1,000,000 old base units per XEC in 2021 — 2 effective decimals.
			'XEC'  => self::def( 'XEC', 'eCash', 'ecash', 'ecash', 'native', 2, 'xec' ),
			'BNB'  => self::def( 'BNB', 'BNB', 'binance-smart-chain', 'binancecoin', 'native', 18, 'bnb', '', 'bsc' ),
			'SOL'  => self::def( 'SOL', 'Solana', 'solana', 'solana', 'native', 9, 'sol' ),
			'TRX'  => self::def( 'TRX', 'TRON', 'tron', 'tron', 'native', 6, 'trx' ),
			'XMR'  => self::def( 'XMR', 'Monero', 'monero', 'monero', 'native', 12, 'xmr' ),
			'XRP'  => self::def( 'XRP', 'Ripple (XRP)', 'ripple', 'ripple', 'native', 6, 'xrp' ),
			'MATIC'=> self::def( 'MATIC', 'Polygon (POL)', 'polygon-pos', 'polygon-ecosystem-token', 'native', 18, 'matic' ),
			'AVAX' => self::def( 'AVAX', 'Avalanche', 'avalanche', 'avalanche-2', 'native', 18, 'avax' ),
			'XLM'  => self::def( 'XLM', 'Stellar', 'stellar', 'stellar', 'native', 7, 'xlm' ),
			'DOT'  => self::def( 'DOT', 'Polkadot', 'polkadot', 'polkadot', 'native', 10, 'dot' ),
			'ATOM' => self::def( 'ATOM', 'Cosmos', 'cosmos', 'cosmos', 'native', 6, 'atom' ),
			'EOS'  => self::def( 'EOS', 'EOS', 'eos', 'eos', 'native', 4, 'eos' ),
			'ETC'  => self::def( 'ETC', 'Ethereum Classic', 'ethereum-classic', 'ethereum-classic', 'native', 18, 'etc' ),
			'ZIL'  => self::def( 'ZIL', 'Zilliqa', 'zilliqa', 'zilliqa', 'native', 12, 'zil' ),
			'FIL'  => self::def( 'FIL', 'Filecoin', 'filecoin', 'filecoin', 'native', 18, 'fil' ),
			'ALGO' => self::def( 'ALGO', 'Algorand', 'algorand', 'algorand', 'native', 6, 'algo' ),
			'HBAR' => self::def( 'HBAR', 'Hedera', 'hedera-hashgraph', 'hedera-hashgraph', 'native', 8, 'hbar' ),
			'CRO'  => self::def( 'CRO', 'Cronos', 'cronos', 'crypto-com-chain', 'native', 18, 'cro' ),
			'FTM'  => self::def( 'FTM', 'Fantom', 'fantom', 'fantom', 'native', 18, 'ftm' ),
			'EGLD' => self::def( 'EGLD', 'MultiversX', 'elrond', 'elrond-erd-2', 'native', 18, 'egld' ),
			'NEAR' => self::def( 'NEAR', 'NEAR Protocol', 'near-protocol', 'near', 'native', 24, 'near' ),

			// Native ETH on L2 / sidechains (auto-verify via Etherscan V2).
			'ETH_ARB'  => self::def( 'ETH_ARB', 'Ethereum (Arbitrum)', 'arbitrum-one', 'ethereum', 'native', 18, 'eth', '', 'arbitrum', 'ETH' ),
			'ETH_OP'   => self::def( 'ETH_OP', 'Ethereum (Optimism)', 'optimistic-ethereum', 'ethereum', 'native', 18, 'eth', '', 'optimism', 'ETH' ),
			'ETH_BASE' => self::def( 'ETH_BASE', 'Ethereum (Base)', 'base', 'ethereum', 'native', 18, 'eth', '', 'base', 'ETH' ),

			// Governance / L2 tokens.
			'ARB'  => self::def( 'ARB', 'Arbitrum', 'arbitrum-one', 'arbitrum', 'erc20', 18, 'eth', '0x912CE59144191C1204E64559FE8253a0e49E6548', 'arbitrum' ),
			'OP'   => self::def( 'OP', 'Optimism', 'optimistic-ethereum', 'optimism', 'erc20', 18, 'eth', '0x4200000000000000000000000000000000000042', 'optimism' ),

			// USD stables (single-network).
			'TUSD' => self::def( 'TUSD', 'TrueUSD', 'ethereum', 'true-usd', 'erc20', 18, 'eth', '0x0000000000085d4780B73119b644AE5ecd22b376', 'ethereum' ),
			'USDP' => self::def( 'USDP', 'Pax Dollar', 'ethereum', 'paxos-standard', 'erc20', 18, 'eth', '0x8E870D67F660D95d5be530380D0eC0bd388289E1', 'ethereum' ),
			'GUSD' => self::def( 'GUSD', 'Gemini Dollar', 'ethereum', 'gemini-dollar', 'erc20', 2, 'eth', '0x056Fd409E1d7A124BD7017459dFEa2F387b6d5Cd', 'ethereum' ),
			'DAI'  => self::def( 'DAI', 'Dai', 'ethereum', 'dai', 'erc20', 18, 'eth', '0x6B175474E89094C44Da98b954EedeAC495271d0F', 'ethereum' ),

			// USDT multi-network.
			'USDT_ETH'   => self::def( 'USDT_ETH', 'Tether (Ethereum)', 'ethereum', 'tether', 'erc20', 6, 'eth', '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'ethereum', 'USDT' ),
			'USDT_ARB'   => self::def( 'USDT_ARB', 'Tether (Arbitrum)', 'arbitrum-one', 'tether', 'erc20', 6, 'eth', '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9', 'arbitrum', 'USDT' ),
			'USDT_OP'    => self::def( 'USDT_OP', 'Tether (Optimism)', 'optimistic-ethereum', 'tether', 'erc20', 6, 'eth', '0x94b008aA00579c1307B0EF2c499aD98a8ce58e58', 'optimism', 'USDT' ),
			'USDT_BNB'   => self::def( 'USDT_BNB', 'Tether (BNB Chain)', 'binance-smart-chain', 'tether', 'bep20', 18, 'bnb', '0x55d398326f99059fF775485246999027B3197955', 'bsc', 'USDT' ),
			'USDT_MATIC' => self::def( 'USDT_MATIC', 'Tether (Polygon)', 'polygon-pos', 'tether', 'erc20', 6, 'eth', '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', 'matic', 'USDT' ),
			'USDT_AVAX'  => self::def( 'USDT_AVAX', 'Tether (Avalanche)', 'avalanche', 'tether', 'erc20', 6, 'eth', '0x9702230A8Ea53601f5cD2dc00fDBc13d4dF4A8c7', 'avax', 'USDT' ),
			'USDT_BASE'  => self::def( 'USDT_BASE', 'Tether (Base)', 'base', 'tether', 'erc20', 6, 'eth', '0xfde4C96c8593536E9F378a799fE87E5bE24637C8', 'base', 'USDT' ),
			'USDT_SOL'   => self::def( 'USDT_SOL', 'Tether (Solana)', 'solana', 'tether', 'spl', 6, 'sol', 'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB', 'solana', 'USDT' ),
			'USDT_TRX'   => self::def( 'USDT_TRX', 'Tether (TRON)', 'tron', 'tether', 'trc20', 6, 'trx', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'tron', 'USDT' ),

			// Other TRON TRC-20 tokens.
			'TUSD_TRX' => self::def( 'TUSD_TRX', 'TrueUSD (TRON)', 'tron', 'true-usd', 'trc20', 18, 'trx', 'TUpMhErZL2fhh4sVNULAbNKLokS4GjC1F4', 'tron', 'TUSD' ),
			'USDD'     => self::def( 'USDD', 'USDD', 'tron', 'usdd', 'trc20', 18, 'trx', 'TXDk8mbtRbXeYuMNS83CfKPaYYT8XWv9Hz', 'tron' ),
			'BTT'      => self::def( 'BTT', 'BitTorrent', 'tron', 'bittorrent', 'trc20', 18, 'trx', 'TAFjULxiVgT4qWk6UZwjqwZXTSaGaqnVp4', 'tron' ),
			'JST'      => self::def( 'JST', 'JUST', 'tron', 'just', 'trc20', 18, 'trx', 'TCFLL5dx5ZJdKnWuesXxi1VPwjLVmWZZy9', 'tron' ),
			'USDJ'     => self::def( 'USDJ', 'JUST Stablecoin', 'tron', 'just-stablecoin', 'trc20', 18, 'trx', 'TMwFHYXLJaRUPeW6421aqXL4ZEzPRFGkGT', 'tron' ),
			'SUN'      => self::def( 'SUN', 'Sun Token', 'tron', 'sun-token', 'trc20', 18, 'trx', 'TSSMHYeV2uE9qYH95DqyoCuNCzEL1NvU3S', 'tron' ),
			'WIN'      => self::def( 'WIN', 'WINkLink', 'tron', 'wink', 'trc20', 6, 'trx', 'TLa2f6VPqDgRE67v1736s7bJ8Ray5wYjU7', 'tron' ),
			'SUNDOG'   => self::def( 'SUNDOG', 'Sundog', 'tron', 'sundog', 'trc20', 18, 'trx', 'TXL6rJbvmjD46zeN1JssfgxvSo99qC8MRT', 'tron' ),

			// Other Solana SPL tokens.
			'ZBC'    => self::def( 'ZBC', 'Zebec Protocol', 'solana', 'zebec-protocol', 'spl', 9, 'sol', 'zebeczgi5fSEtbpfQKVZKCJ3WgYXxjkMUkNNx7fLKAF', 'solana' ),
			'GARI'   => self::def( 'GARI', 'Gari Network', 'solana', 'gari-network', 'spl', 9, 'sol', 'CKaKtYvz6dKPyMvYq9Rh3UBrnNqYZAyd7iF4hJtjUvks', 'solana' ),
			'MEW'    => self::def( 'MEW', 'cat in a dogs world', 'solana', 'cat-in-a-dogs-world', 'spl', 5, 'sol', 'MEW1gQWJ3nEXg2qgERiKu7FAFj79PHvQVREQUzScPP5', 'solana' ),
			'PONKE'  => self::def( 'PONKE', 'Ponke', 'solana', 'ponke', 'spl', 9, 'sol', '5z3EqYQo9HiCEs3R84RCDMu2n7anpDMxRhdK8PSWmrRC', 'solana' ),
			'MYRO'   => self::def( 'MYRO', 'Myro', 'solana', 'myro', 'spl', 6, 'sol', 'Bm3dgkZrjH7eaz1GBH1R2ZHkp7nZUoPNJhjekoxepump', 'solana' ),

			// USDC multi-network.
			'USDC_ETH'   => self::def( 'USDC_ETH', 'USD Coin (Ethereum)', 'ethereum', 'usd-coin', 'erc20', 6, 'eth', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'ethereum', 'USDC' ),
			'USDC_ARB'   => self::def( 'USDC_ARB', 'USD Coin (Arbitrum)', 'arbitrum-one', 'usd-coin', 'erc20', 6, 'eth', '0xaf88d065e77c8cC2239327C5EDb3A432268e5831', 'arbitrum', 'USDC' ),
			'USDC_OP'    => self::def( 'USDC_OP', 'USD Coin (Optimism)', 'optimistic-ethereum', 'usd-coin', 'erc20', 6, 'eth', '0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85', 'optimism', 'USDC' ),
			'USDC_BNB'   => self::def( 'USDC_BNB', 'USD Coin (BNB Chain)', 'binance-smart-chain', 'usd-coin', 'bep20', 18, 'bnb', '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', 'bsc', 'USDC' ),
			'USDC_MATIC' => self::def( 'USDC_MATIC', 'USD Coin (Polygon)', 'polygon-pos', 'usd-coin', 'erc20', 6, 'eth', '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 'matic', 'USDC' ),
			'USDC_AVAX'  => self::def( 'USDC_AVAX', 'USD Coin (Avalanche)', 'avalanche', 'usd-coin', 'erc20', 6, 'eth', '0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E', 'avax', 'USDC' ),
			'USDC_BASE'  => self::def( 'USDC_BASE', 'USD Coin (Base)', 'base', 'usd-coin', 'erc20', 6, 'eth', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', 'base', 'USDC' ),
			'USDC_SOL'   => self::def( 'USDC_SOL', 'USD Coin (Solana)', 'solana', 'usd-coin', 'spl', 6, 'sol', 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', 'solana', 'USDC' ),
			'USDC_TRX'   => self::def( 'USDC_TRX', 'USD Coin (TRON)', 'tron', 'usd-coin', 'trc20', 6, 'trx', 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8', 'tron', 'USDC' ),

			// DAI multi-network.
			'DAI_ARB'   => self::def( 'DAI_ARB', 'Dai (Arbitrum)', 'arbitrum-one', 'dai', 'erc20', 18, 'eth', '0xDA10009cBd5D07dd0CeCc66161FC93D7c9000da1', 'arbitrum', 'DAI' ),
			'DAI_OP'    => self::def( 'DAI_OP', 'Dai (Optimism)', 'optimistic-ethereum', 'dai', 'erc20', 18, 'eth', '0xDA10009cBd5D07dd0CeCc66161FC93D7c9000da1', 'optimism', 'DAI' ),
			'DAI_MATIC' => self::def( 'DAI_MATIC', 'Dai (Polygon)', 'polygon-pos', 'dai', 'erc20', 18, 'eth', '0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063', 'matic', 'DAI' ),
			'DAI_BASE'  => self::def( 'DAI_BASE', 'Dai (Base)', 'base', 'dai', 'erc20', 18, 'eth', '0x50c5725949A6F0c72E6C4a641F24049A917DB0Cb', 'base', 'DAI' ),

			// Major Ethereum tokens (auto-verify).
			'WBTC' => self::def( 'WBTC', 'Wrapped Bitcoin', 'ethereum', 'wrapped-bitcoin', 'erc20', 8, 'eth', '0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599', 'ethereum' ),
			'LINK' => self::def( 'LINK', 'Chainlink', 'ethereum', 'chainlink', 'erc20', 18, 'eth', '0x514910771AF9Ca656af840dff83E8264EcF986CA', 'ethereum' ),
			'UNI'  => self::def( 'UNI', 'Uniswap', 'ethereum', 'uniswap', 'erc20', 18, 'eth', '0x1f9840a85d5aF5bf1D1762F925BDADdC4201F984', 'ethereum' ),
			'AAVE' => self::def( 'AAVE', 'Aave', 'ethereum', 'aave', 'erc20', 18, 'eth', '0x7Fc66500c84A76Ad7e9c93437bFc5Ac33E2DDaE9', 'ethereum' ),
			'MKR'  => self::def( 'MKR', 'Maker', 'ethereum', 'maker', 'erc20', 18, 'eth', '0x9f8F72aA9304c8B593d555F12eF6589cC3A579A2', 'ethereum' ),
			'LDO'  => self::def( 'LDO', 'Lido DAO', 'ethereum', 'lido-dao', 'erc20', 18, 'eth', '0x5A98FcBEA516Cf06857215779Fd812CA3beF1B32', 'ethereum' ),
			'CRV'  => self::def( 'CRV', 'Curve DAO', 'ethereum', 'curve-dao-token', 'erc20', 18, 'eth', '0xD533a949740bb3306d119CC777fa900bA034cd52', 'ethereum' ),
			'COMP' => self::def( 'COMP', 'Compound', 'ethereum', 'compound-governance-token', 'erc20', 18, 'eth', '0xc00e94Cb662C3520282E6f5717214004A7f26888', 'ethereum' ),
			'APE'  => self::def( 'APE', 'ApeCoin', 'ethereum', 'apecoin', 'erc20', 18, 'eth', '0x4d224452801ACEd8B2F0aebE155379bb5D594381', 'ethereum' ),
			'SHIB' => self::def( 'SHIB', 'Shiba Inu', 'ethereum', 'shiba-inu', 'erc20', 18, 'eth', '0x95aD61b0a150d79219dCF64E1E6Cc01f0B64C4cE', 'ethereum' ),
			'PEPE' => self::def( 'PEPE', 'Pepe', 'ethereum', 'pepe', 'erc20', 18, 'eth', '0x6982508145454Ce325dDbE47a25d4ec3d2311933', 'ethereum' ),
			'AXS'  => self::def( 'AXS', 'Axie Infinity', 'ethereum', 'axie-infinity', 'erc20', 18, 'eth', '0xBB0E17EF65F82Ab018d8EDd776e8DD940327B28b', 'ethereum' ),
			'MANA' => self::def( 'MANA', 'Decentraland', 'ethereum', 'decentraland', 'erc20', 18, 'eth', '0x0F5D2fB29fb7d3CFeE444a200298f468908cC942', 'ethereum' ),
			'SAND' => self::def( 'SAND', 'The Sandbox', 'ethereum', 'the-sandbox', 'erc20', 18, 'eth', '0x3845badAde8e6dFF049820680d1F14bD3903a5d0', 'ethereum' ),
			'ATA'  => self::def( 'ATA', 'Automata', 'ethereum', 'automata', 'erc20', 18, 'eth', '0xA2120b9e674d3fC3875f415A7DF52e382F141225', 'ethereum' ),
			'FTT'  => self::def( 'FTT', 'FTX Token', 'ethereum', 'ftx-token', 'erc20', 18, 'eth', '0x50D1c9771902476076eCFc8B2A83Ad6b9355a4c9', 'ethereum' ),
			'CAKE' => self::def( 'CAKE', 'PancakeSwap', 'binance-smart-chain', 'pancakeswap-token', 'bep20', 18, 'bnb', '0x0E09FaBB73Bd3Ade0a17ECC321fD13a19e81cE82', 'bsc' ),

			// Extra-chain token variants.
			'LINK_ARB' => self::def( 'LINK_ARB', 'Chainlink (Arbitrum)', 'arbitrum-one', 'chainlink', 'erc20', 18, 'eth', '0xf97f4df75117a78c1A5a0DBb814Af92458539FB4', 'arbitrum', 'LINK' ),
			'LINK_OP'  => self::def( 'LINK_OP', 'Chainlink (Optimism)', 'optimistic-ethereum', 'chainlink', 'erc20', 18, 'eth', '0x350a791Bfc2C21F9Ed5d10980Dad2e2638ffa7f6', 'optimism', 'LINK' ),
			'LINK_BNB' => self::def( 'LINK_BNB', 'Chainlink (BNB Chain)', 'binance-smart-chain', 'chainlink', 'bep20', 18, 'bnb', '0xF8A0BF9cF54Bb92F17374d9e9A321E6a111a51bD', 'bsc', 'LINK' ),
			'UNI_ARB'  => self::def( 'UNI_ARB', 'Uniswap (Arbitrum)', 'arbitrum-one', 'uniswap', 'erc20', 18, 'eth', '0xFa7F8980b0f1E64A2062791cc3b0879522D1F4B4', 'arbitrum', 'UNI' ),
			'UNI_BNB'  => self::def( 'UNI_BNB', 'Uniswap (BNB Chain)', 'binance-smart-chain', 'uniswap', 'bep20', 18, 'bnb', '0xBf5140A22578168FD72BBf46B7b5C406cF0A4cF3', 'bsc', 'UNI' ),
			'AVAX_ETH' => self::def( 'AVAX_ETH', 'Avalanche (Ethereum)', 'ethereum', 'avalanche-2', 'erc20', 18, 'eth', '0x85f138bfEE4ef8e540890CFb48F620571d67Eda3', 'ethereum', 'AVAX' ),
			'AAVE_ARB' => self::def( 'AAVE_ARB', 'Aave (Arbitrum)', 'arbitrum-one', 'aave', 'erc20', 18, 'eth', '0xba5DdD1f9d7F570dc94a51479a533F7541305652', 'arbitrum', 'AAVE' ),
			'AAVE_OP'  => self::def( 'AAVE_OP', 'Aave (Optimism)', 'optimistic-ethereum', 'aave', 'erc20', 18, 'eth', '0x76FB31fb4af56892A25e32cFC43De717950c9278', 'optimism', 'AAVE' ),

			// Additional Ethereum ERC-20 tokens (all contracts verified against CoinGecko's platform data).
			'MX'     => self::def( 'MX', 'MX Token', 'ethereum', 'mx-token', 'erc20', 18, 'eth', '0x11eEF04C884E24d9B7B4760e7476D06ddF797f36', 'ethereum' ),
			'LEASH'  => self::def( 'LEASH', 'Doge Killer (LEASH)', 'ethereum', 'leash', 'erc20', 18, 'eth', '0x27C70Cd1946795B66be9d954418546998b546634', 'ethereum' ),
			'FUN'    => self::def( 'FUN', 'FUNToken', 'ethereum', 'funfair', 'erc20', 8, 'eth', '0x419D0d8BdD9aF5e606Ae2232ed285Aff190E711b', 'ethereum' ),
			'KNC'    => self::def( 'KNC', 'Kyber Network Crystal', 'ethereum', 'kyber-network-crystal', 'erc20', 18, 'eth', '0xdeFA4e8a7bcBA345F687a2f1456F5Edd9CE97202', 'ethereum' ),
			'BAT'    => self::def( 'BAT', 'Basic Attention Token', 'ethereum', 'basic-attention-token', 'erc20', 18, 'eth', '0x0D8775F648430679A709E98d2b0Cb6250d2887EF', 'ethereum' ),
			'FLOKI'  => self::def( 'FLOKI', 'Floki', 'ethereum', 'floki', 'erc20', 9, 'eth', '0xcf0C122c6b73ff809C693DB761e7BaeBe62b6a2E', 'ethereum' ),
			'OMG'    => self::def( 'OMG', 'OMG Network', 'ethereum', 'omisego', 'erc20', 18, 'eth', '0xd26114cd6EE289AccF82350c8d8487fedB8A0C07', 'ethereum' ),
			'CHZ'    => self::def( 'CHZ', 'Chiliz', 'ethereum', 'chiliz', 'erc20', 18, 'eth', '0x3506424F91fD33084466F402d5D97f05F8e3b4AF', 'ethereum' ),
			'GT'     => self::def( 'GT', 'Gate', 'ethereum', 'gatechain-token', 'erc20', 18, 'eth', '0xE66747a101bFF2dBA3697199DCce5b743b454759', 'ethereum' ),
			'XYO'    => self::def( 'XYO', 'XYO Network', 'ethereum', 'xyo-network', 'erc20', 18, 'eth', '0x55296f69f40Ea6d20E478533C15A6B08B654E758', 'ethereum' ),
			'ARPA'   => self::def( 'ARPA', 'ARPA', 'ethereum', 'arpa', 'erc20', 18, 'eth', '0xBA50933C268F567BDC86E1aC131BE072C6B0b71a', 'ethereum' ),
			'BEL'    => self::def( 'BEL', 'Bella Protocol', 'ethereum', 'bella-protocol', 'erc20', 18, 'eth', '0xA91ac63D040dEB1b7A5E4d4134aD23eb0ba07e14', 'ethereum' ),
			'BONE'   => self::def( 'BONE', 'Bone ShibaSwap', 'ethereum', 'bone-shibaswap', 'erc20', 18, 'eth', '0x9813037ee2218799597d83D4a5B6F3b6778218d9', 'ethereum' ),
			'OKB'    => self::def( 'OKB', 'OKB', 'ethereum', 'okb', 'erc20', 18, 'eth', '0x75231F58b43240C9718Dd58B4967c5114342a86c', 'ethereum' ),
			'ENJ'    => self::def( 'ENJ', 'Enjin Coin', 'ethereum', 'enjincoin', 'erc20', 18, 'eth', '0xF629cBd94d3791C9250152BD8dfBDF380E2a3B9c', 'ethereum' ),
			'REP'    => self::def( 'REP', 'Augur', 'ethereum', 'augur', 'erc20', 18, 'eth', '0x221657776846890989a759BA2973e427DfF5C9bB', 'ethereum' ),
			'COTI'   => self::def( 'COTI', 'COTI', 'ethereum', 'coti', 'erc20', 18, 'eth', '0xDDB3422497E61e13543BeA06989C0789117555c5', 'ethereum' ),
			'OCEAN'  => self::def( 'OCEAN', 'Ocean Protocol', 'ethereum', 'ocean-protocol', 'erc20', 18, 'eth', '0x967da4048cD07aB37855c090aAF366e4ce1b9F48', 'ethereum' ),
			'YFI'    => self::def( 'YFI', 'yearn.finance', 'ethereum', 'yearn-finance', 'erc20', 18, 'eth', '0x0bc529c00C6401aEF6D220BE8C6Ea1667F6Ad93e', 'ethereum' ),
			'DAO'    => self::def( 'DAO', 'DAO Maker', 'ethereum', 'dao-maker', 'erc20', 18, 'eth', '0x0f51bb10119727a7e5eA3538074fb341F56B09Ad', 'ethereum' ),
			'CHR'    => self::def( 'CHR', 'Chromia', 'ethereum', 'chromaway', 'erc20', 6, 'eth', '0x8A2279d4A90B6fe1C4B30fa660cC9f926797bAa2', 'ethereum' ),
			'FRONT'  => self::def( 'FRONT', 'Frontier', 'ethereum', 'frontier-token', 'erc20', 18, 'eth', '0xf8C3527CC04340b208C854E985240c02F7B7793f', 'ethereum' ),
			'CTSI'   => self::def( 'CTSI', 'Cartesi', 'ethereum', 'cartesi', 'erc20', 18, 'eth', '0x491604c0FDF08347Dd1fa4Ee062a822A5DD06B5D', 'ethereum' ),
			'NOW'    => self::def( 'NOW', 'ChangeNOW', 'ethereum', 'changenow', 'erc20', 8, 'eth', '0xE9A95d175a5f4C9369f3b74222402eB1b837693B', 'ethereum' ),
			'SRK'    => self::def( 'SRK', 'SparkPoint', 'ethereum', 'sparkpoint', 'erc20', 18, 'eth', '0x0488401c3F535193Fa8Df029d9fFe615A06E74e6', 'ethereum' ),
			'SUPER'  => self::def( 'SUPER', 'SuperVerse', 'ethereum', 'superfarm', 'erc20', 18, 'eth', '0xe53EC727dbDEb9E2d5456c3be40cFF031AB40A55', 'ethereum' ),
			'HOGE'   => self::def( 'HOGE', 'Hoge Finance', 'ethereum', 'hoge-finance', 'erc20', 9, 'eth', '0xfAd45E47083e4607302aa43c65fB3106F1cd7607', 'ethereum' ),
			'AVA'    => self::def( 'AVA', 'AVA (Travala)', 'ethereum', 'concierge-io', 'erc20', 18, 'eth', '0xa6C0c097741D55eCd9a3A7dEF3a8253FD022ceB9', 'ethereum' ),
			'1INCH'  => self::def( '1INCH', '1inch Network', 'ethereum', '1inch', 'erc20', 18, 'eth', '0x111111111117dC0aa78b770fA6A738034120C302', 'ethereum' ),
			'GALA'   => self::def( 'GALA', 'GALA', 'ethereum', 'gala', 'erc20', 8, 'eth', '0xd1d2Eb1B1e90B638588728b4130137D262C87caE', 'ethereum' ),
			'NTVRK'  => self::def( 'NTVRK', 'Netvrk', 'ethereum', 'netvrk', 'erc20', 18, 'eth', '0x52498f8d9791736f1D6398FE95bA3bd868114D10', 'ethereum' ),
			'JASMY'  => self::def( 'JASMY', 'JasmyCoin', 'ethereum', 'jasmycoin', 'erc20', 18, 'eth', '0x7420B4b9a0110cdC71fB720908340C03F9Bc03ec', 'ethereum' ),
			'ILV'    => self::def( 'ILV', 'Illuvium', 'ethereum', 'illuvium', 'erc20', 18, 'eth', '0x767FE9EDC9E0dF98E07454847909b5E959D7ca0E', 'ethereum' ),
			'CUDOS'  => self::def( 'CUDOS', 'Cudos', 'ethereum', 'cudos', 'erc20', 18, 'eth', '0x817bbDbC3e8A1204F3691d14bB44992841e3dB35', 'ethereum' ),
			'XCAD'   => self::def( 'XCAD', 'XCAD Network', 'ethereum', 'xcad-network', 'erc20', 18, 'eth', '0x7659CE147D0e714454073a5dd7003544234b6Aa0', 'ethereum' ),
			'GRT'    => self::def( 'GRT', 'The Graph', 'ethereum', 'the-graph', 'erc20', 18, 'eth', '0xc944E90C64B2c07662A292be6244BDf05Cda44a7', 'ethereum' ),
			'XAUT'   => self::def( 'XAUT', 'Tether Gold', 'ethereum', 'tether-gold', 'erc20', 6, 'eth', '0x68749665FF8D2d112Fa859AA293F07A622782F38', 'ethereum' ),
			'CVC'    => self::def( 'CVC', 'Civic', 'ethereum', 'civic', 'erc20', 8, 'eth', '0x41e5560054824eA6B0732E656E3Ad64E20e94E45', 'ethereum' ),
			'CULT'   => self::def( 'CULT', 'Cult DAO', 'ethereum', 'cult-dao', 'erc20', 18, 'eth', '0xf0f9D895aCa5c8678f706FB8216fa22957685A13', 'ethereum' ),
			'HEX'    => self::def( 'HEX', 'HEX', 'ethereum', 'hex', 'erc20', 8, 'eth', '0x2b591e99afE9f32eAA6214f7B7629768c40Eeb39', 'ethereum' ),
			'VERSE'  => self::def( 'VERSE', 'Verse', 'ethereum', 'verse-bitcoin', 'erc20', 18, 'eth', '0x249cA82617eC3DfB2589c4c17ab7EC9765350a18', 'ethereum' ),
			'PLX'    => self::def( 'PLX', 'Pullix', 'ethereum', 'pullix', 'erc20', 18, 'eth', '0x1cC7047e15825F639e0752EB1B89e4225f5327f2', 'ethereum' ),
			'SIDUS'  => self::def( 'SIDUS', 'Sidus', 'ethereum', 'sidus', 'erc20', 18, 'eth', '0x549020a9Cb845220D66d3E9c6D9F9eF61C981102', 'ethereum' ),
			'BANANA' => self::def( 'BANANA', 'Banana Gun', 'ethereum', 'banana-gun', 'erc20', 18, 'eth', '0x38E68A37E401F7271568CecaAc63c6B1e19130B4', 'ethereum' ),
			'USDE'   => self::def( 'USDE', 'Ethena USDe', 'ethereum', 'ethena-usde', 'erc20', 18, 'eth', '0x4c9EDD5852cd905f086C759E8383e09bff1E68B3', 'ethereum' ),
			'RJV'    => self::def( 'RJV', 'Rejuve.AI', 'ethereum', 'rejuve-ai', 'erc20', 6, 'eth', '0xa1f410F13B6007fca76833eE7EB58478D47bC5eF', 'ethereum' ),
			'CGPT'   => self::def( 'CGPT', 'ChainGPT', 'ethereum', 'chaingpt', 'erc20', 18, 'eth', '0x25931894a86D47441213199621F1F2994e1c39Aa', 'ethereum' ),
			'ZRO'    => self::def( 'ZRO', 'LayerZero', 'ethereum', 'layerzero', 'erc20', 18, 'eth', '0x6985884C4392D348587B19cb9eAAf157F13271cd', 'ethereum' ),
			'G'      => self::def( 'G', 'Gravity (by Galxe)', 'ethereum', 'g-token', 'erc20', 18, 'eth', '0x9C7BEBa8F6eF6643aBd725e45a4E8387eF260649', 'ethereum' ),
			'TLOS'   => self::def( 'TLOS', 'Telos', 'ethereum', 'telos', 'erc20', 18, 'eth', '0x193f4A4a6ea24102F49b931dEEEB931F6E32405D', 'ethereum' ),
			'PYUSD'  => self::def( 'PYUSD', 'PayPal USD', 'ethereum', 'paypal-usd', 'erc20', 6, 'eth', '0x6c3ea9036406852006290770BEdFcAbA0e23A0e8', 'ethereum' ),
			'EURT'   => self::def( 'EURT', 'Euro Tether', 'ethereum', 'tether-eurt', 'erc20', 6, 'eth', '0xC581b735A1688071A1746c968e0798D642EDE491', 'ethereum' ),

			// Second-network variants of tokens added above (same project, address already
			// verified as part of that lookup — no new research needed for these).
			'FLOKIBSC' => self::def( 'FLOKIBSC', 'Floki (BSC)', 'binance-smart-chain', 'floki', 'bep20', 9, 'bnb', '0xfb5B838b6cfEEdC2873aB27866079AC55363D37E', 'bsc' ),
			'1INCHBSC' => self::def( '1INCHBSC', '1inch Network (BSC)', 'binance-smart-chain', '1inch', 'bep20', 18, 'bnb', '0x111111111117dC0aa78b770fA6A738034120C302', 'bsc' ),
			'BTTBSC'   => self::def( 'BTTBSC', 'BitTorrent (BSC)', 'binance-smart-chain', 'bittorrent', 'bep20', 18, 'bnb', '0x352Cb5E19b12FC216548a2677bD0fce83BAe434B', 'bsc' ),
			'ZRO_ARB'  => self::def( 'ZRO_ARB', 'LayerZero (Arbitrum)', 'arbitrum-one', 'layerzero', 'erc20', 18, 'eth', '0x6985884C4392D348587B19cb9eAAf157F13271cd', 'arbitrum', 'ZRO' ),
			'CGPT_BSC' => self::def( 'CGPT_BSC', 'ChainGPT (BSC)', 'binance-smart-chain', 'chaingpt', 'bep20', 18, 'bnb', '0x9840652DC04fb9db2C43853633f0F62BE6f00f98', 'bsc', 'CGPT' ),
			'G_BSC'    => self::def( 'G_BSC', 'Gravity (by Galxe) (BSC)', 'binance-smart-chain', 'g-token', 'bep20', 18, 'bnb', '0x9C7BEBa8F6eF6643aBd725e45a4E8387eF260649', 'bsc', 'G' ),
			'RJV_BSC'  => self::def( 'RJV_BSC', 'Rejuve.AI (BSC)', 'binance-smart-chain', 'rejuve-ai', 'bep20', 6, 'bnb', '0x602b6C6cCE5f95c00603bd07D8Fa7EBaF3747d44', 'bsc', 'RJV' ),

			// Final round: newly researched (not second-network variants of earlier entries).
			'BABYDOGE' => self::def( 'BABYDOGE', 'Baby Doge Coin', 'binance-smart-chain', 'baby-doge-coin', 'bep20', 9, 'bnb', '0xc748673057861a797275CD8A068AbB95A902e8de', 'bsc' ),
			'HOT'      => self::def( 'HOT', 'Holo', 'ethereum', 'holotoken', 'erc20', 18, 'eth', '0x6c6EE5e31d828De241282B9606C8e98Ea48526E2', 'ethereum' ),
			'POOLX'    => self::def( 'POOLX', 'Poolz Finance', 'binance-smart-chain', 'poolz-finance-2', 'bep20', 18, 'bnb', '0xbaEa9ABA1454Df334943951D51116aE342EAb255', 'bsc' ),
			'SXP'      => self::def( 'SXP', 'Solar', 'binance-smart-chain', 'swipe', 'bep20', 18, 'bnb', '0x47BEAD2563dCBf3bF2c9407fEa4dC236fabA485A', 'bsc' ),
			// Injective's native chain is Cosmos-based; this is the ERC-20-wrapped form on
			// Ethereum only — a customer sending native-chain INJ would not be detected here.
			'INJ'      => self::def( 'INJ', 'Injective (bridged)', 'ethereum', 'injective-protocol', 'erc20', 18, 'eth', '0xe28b3B32B6c345A34FF64674606124Dd5Aceca30', 'ethereum' ),
			'TRVL'   => self::def( 'TRVL', 'Dtravel', 'ethereum', 'dtravel', 'erc20', 18, 'eth', '0xd47BDF574B4F76210ed503e0EFe81B58Aa061f3d', 'ethereum' ),

			// Additional BNB Chain BEP-20 tokens (all contracts verified against CoinGecko's platform data).
			'AVABSC'    => self::def( 'AVABSC', 'AVA (Travala) Bridged (BSC)', 'binance-smart-chain', 'ava-foundation-bridged-ava-bsc', 'bep20', 18, 'bnb', '0xD9483ea7214FCFd89B4Fb8f513B544920e315A52', 'bsc' ),
			'TKO'       => self::def( 'TKO', 'Tokocrypto', 'binance-smart-chain', 'tokocrypto', 'bep20', 18, 'bnb', '0x9f589E3eabe42ebC94A44727b3f3531C0c877809', 'bsc' ),
			'RACA'      => self::def( 'RACA', 'Radio Caca', 'binance-smart-chain', 'radio-caca', 'bep20', 18, 'bnb', '0x12BB890508c125661E03b09EC06E404bc9289040', 'bsc' ),
			'BRISEBSC'  => self::def( 'BRISEBSC', 'Bitgert', 'binance-smart-chain', 'bitrise-token', 'bep20', 9, 'bnb', '0x8FFf93E810a2eDaaFc326eDEe51071DA9d398e83', 'bsc' ),
			'SFUND'     => self::def( 'SFUND', 'Seedify.fund', 'binance-smart-chain', 'seedify-fund', 'bep20', 18, 'bnb', '0xFfda10b7fD9Cf172E0502A6bc0e5e355516C5232', 'bsc' ),
			'SHIBBSC'   => self::def( 'SHIBBSC', 'Shiba Inu (BSC)', 'binance-smart-chain', 'binance-peg-shib', 'bep20', 18, 'bnb', '0x2859e4544C4bB03966803b044A93563Bd2D0DD4D', 'bsc' ),
			'ETHBSC'    => self::def( 'ETHBSC', 'Ethereum (BSC)', 'binance-smart-chain', 'binance-peg-weth', 'bep20', 18, 'bnb', '0x2170Ed0880ac9A755fd29B2688956BD959F933F8', 'bsc' ),
			'HOTCROSS'  => self::def( 'HOTCROSS', 'Hot Cross', 'binance-smart-chain', 'hot-cross', 'bep20', 18, 'bnb', '0x4fa7163E153419E0E1064e418dD7A99314Ed27B6', 'bsc' ),
			'QUACK'     => self::def( 'QUACK', 'RichQUACK', 'binance-smart-chain', 'richquack', 'bep20', 9, 'bnb', '0xD74b782E05AA25c50e7330Af541d46E18f36661c', 'bsc' ),
			'BRGBSC'    => self::def( 'BRGBSC', 'Bridge AI', 'binance-smart-chain', 'bridge-oracle', 'bep20', 18, 'bnb', '0xAc45475Abe6f9516E233A824Aa013933b61Bbb5c', 'bsc' ),
			'ID'        => self::def( 'ID', 'SPACE ID', 'binance-smart-chain', 'space-id', 'bep20', 18, 'bnb', '0x2dfF88A56767223A5529eA5960Da7A3F5f766406', 'bsc' ),
			'C98'       => self::def( 'C98', 'Coin98', 'binance-smart-chain', 'coin98', 'bep20', 18, 'bnb', '0xaEC945e04baF28b135Fa7c640f624f8D90F1C3a6', 'bsc' ),

			// Additional Solana SPL, Polygon, Base, and Avalanche tokens.
			'CNS'        => self::def( 'CNS', 'Centric Swap', 'solana', 'centric-swap', 'spl', 8, 'sol', '2jUSPvEuxSxJ6nGpUHyXVAcNxyhugPC1iBeNjhTXzAUy', 'solana' ),
			'MATICUSDCE' => self::def( 'MATICUSDCE', 'USD Coin Bridged (Polygon PoS)', 'polygon-pos', 'bridged-usdc-polygon-pos-bridge', 'erc20', 6, 'eth', '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', 'matic' ),
			'BRETT'      => self::def( 'BRETT', 'Brett', 'base', 'based-brett', 'erc20', 18, 'eth', '0x532f27101965dd16442E59d40670FaF5eBB142E4', 'base' ),
			'FITFI'      => self::def( 'FITFI', 'Step App', 'avalanche', 'step-app-fitfi', 'erc20', 18, 'eth', '0x714f020c54cC9D104b6F4f6998C63cE2a31D1888', 'avax' ),
		);

		/**
		 * Filter the full coin catalog.
		 *
		 * @param array $coins Coin definitions.
		 */
		return apply_filters( 'xdwp_coins', $coins );
	}

	/**
	 * Build a coin definition array.
	 *
	 * @param string $id           Coin ID.
	 * @param string $name         Display name.
	 * @param string $platform     CoinGecko platform / network slug.
	 * @param string $coingecko_id CoinGecko asset id.
	 * @param string $type         native|erc20|bep20|trc20|spl.
	 * @param int    $decimals     Decimals.
	 * @param string $uri_scheme   Payment URI scheme prefix.
	 * @param string $contract     Token contract (optional).
	 * @param string $verifier     Verifier family key.
	 * @param string $group        Display group (e.g. USDT).
	 * @return array<string, mixed>
	 */
	private static function def( $id, $name, $platform, $coingecko_id, $type, $decimals, $uri_scheme, $contract = '', $verifier = '', $group = '' ) {
		$parts    = explode( '_', $id );
		$symbol   = $group ? $group : $parts[0];
		$verifier = $verifier ? $verifier : strtolower( $parts[0] );

		return array(
			'id'           => $id,
			'symbol'       => $symbol,
			'name'         => $name,
			'platform'     => $platform,
			'coingecko_id' => $coingecko_id,
			'type'         => $type,
			'decimals'     => (int) $decimals,
			'uri_scheme'   => $uri_scheme,
			'contract'     => $contract,
			'verifier'     => $verifier,
			'group'        => $group ? $group : $symbol,
			'network'      => isset( $parts[1] ) ? $parts[1] : $parts[0],
		);
	}

	/**
	 * Get one coin by ID.
	 *
	 * @param string $id Coin ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Coins enabled in settings that also have at least one wallet.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_payable() {
		$enabled = Xdwp_Settings::get( 'enabled_coins', array() );
		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}

		$payable = array();
		foreach ( $enabled as $coin_id ) {
			$coin = self::get( $coin_id );
			if ( ! $coin ) {
				continue;
			}
			$wallets = Xdwp_Wallets::get_addresses( $coin_id );
			if ( empty( $wallets ) ) {
				continue;
			}
			$payable[ $coin_id ] = $coin;
		}

		return $payable;
	}

	/**
	 * Public URL for a bundled coin SVG (Cryptoniq-style icons).
	 *
	 * @param string $slug Icon file slug without extension (btc, eth, usdt-round, …).
	 * @return string
	 */
	public static function icon_url( $slug ) {
		$slug = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $slug ) );
		if ( '' === $slug ) {
			return '';
		}
		$file = XDWP_PATH . 'assets/svg/coins/' . $slug . '.svg';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		return XDWP_URL . 'assets/svg/coins/' . $slug . '.svg';
	}

	/**
	 * Icon + optional network badge for checkout coin tiles.
	 *
	 * @param string $coin_id Coin ID.
	 * @return array{icon:string,badge:string,slug:string}
	 */
	public static function icon_meta( $coin_id ) {
		$coin   = self::get( $coin_id );
		$symbol = $coin ? strtoupper( (string) $coin['symbol'] ) : strtoupper( (string) $coin_id );
		$id     = strtoupper( (string) $coin_id );

		$network_map = array(
			'ETH'   => 'eth',
			'ARB'   => 'arb',
			'OP'    => 'op',
			'BNB'   => 'bnb',
			'SOL'   => 'sol',
			'TRX'   => 'trx',
			'MATIC' => 'matic',
			'AVAX'  => 'avax',
			'BASE'  => 'base',
		);

		$base_map = array(
			'BTC'   => 'btc',
			'BCH'   => 'bch',
			'ETH'   => 'eth',
			'LTC'   => 'ltc',
			'DOGE'  => 'doge',
			'BNB'   => 'bnb',
			'SOL'   => 'sol',
			'TRX'   => 'trx',
			'ARB'   => 'arb',
			'OP'    => 'op',
			'MATIC' => 'matic',
			'POL'   => 'matic',
			'AVAX'  => 'avax',
			'USDT'  => 'usdt-round',
			'USDC'  => 'usdc',
			'DAI'   => 'dai',
			'WBTC'  => 'wbtc',
			'XMR'   => 'xmr',
			'XRP'   => 'xrp',
			'XLM'   => 'xlm',
			'LINK'  => 'link',
			'UNI'   => 'uni',
			'AAVE'  => 'aave',
			'MKR'   => 'mkr',
			'LDO'   => 'ldo',
			'CRV'   => 'crv',
			'COMP'  => 'comp',
			'APE'   => 'ape',
			'SHIB'  => 'shib',
			'PEPE'  => 'pepe',
			'DOT'   => 'dot',
			'ATOM'  => 'atom',
			'EOS'   => 'eos',
			'ETC'   => 'etc',
			'ZIL'   => 'zil',
			'FIL'   => 'fil',
			'ALGO'  => 'algo',
			'HBAR'  => 'hbar',
			'CRO'   => 'cro',
			'FTM'   => 'ftm',
			'NEAR'  => 'near',
			'AXS'   => 'axs',
			'MANA'  => 'mana',
			'SAND'  => 'sand',
			'CAKE'  => 'cake',
			'FTT'   => 'ftt',
			'TUSD'  => 'tusd',
			'GUSD'  => 'gusd',
			'USDP'  => 'usdp',
			'ATA'   => 'ata',
			'EGLD'  => 'egld',
		);

		$icon_slug  = '';
		$badge_slug = '';

		// Multi-network stables / tokens: main icon + network badge.
		if ( preg_match( '/^(USDT|USDC|DAI)_(.+)$/', $id, $m ) ) {
			$icon_slug  = isset( $base_map[ $m[1] ] ) ? $base_map[ $m[1] ] : strtolower( $m[1] );
			$badge_slug = isset( $network_map[ $m[2] ] ) ? $network_map[ $m[2] ] : strtolower( $m[2] );
		} elseif ( preg_match( '/^(ETH)_(ARB|OP|BASE)$/', $id, $m ) ) {
			$icon_slug  = 'eth';
			$badge_slug = isset( $network_map[ $m[2] ] ) ? $network_map[ $m[2] ] : strtolower( $m[2] );
		} elseif ( preg_match( '/^(LINK|UNI|AVAX|AAVE)_(ETH|ARB|OP|BNB)$/', $id, $m ) ) {
			$icon_slug  = isset( $base_map[ $m[1] ] ) ? $base_map[ $m[1] ] : strtolower( $m[1] );
			$badge_slug = isset( $network_map[ $m[2] ] ) ? $network_map[ $m[2] ] : strtolower( $m[2] );
		} elseif ( 'ARB' === $id ) {
			$icon_slug = 'arb';
		} elseif ( 'OP' === $id ) {
			$icon_slug = 'op';
		} elseif ( isset( $base_map[ $symbol ] ) ) {
			$icon_slug = $base_map[ $symbol ];
		} elseif ( isset( $base_map[ $id ] ) ) {
			$icon_slug = $base_map[ $id ];
		} else {
			$guess = strtolower( preg_replace( '/[^A-Z0-9]/', '', $symbol ) );
			if ( $guess && self::icon_url( $guess ) ) {
				$icon_slug = $guess;
			}
		}

		$icon  = $icon_slug ? self::icon_url( $icon_slug ) : '';
		$badge = $badge_slug ? self::icon_url( $badge_slug ) : '';

		return array(
			'icon'  => $icon,
			'badge' => $badge,
			'slug'  => $icon_slug,
		);
	}

	/**
	 * Group coins for admin UI.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function grouped() {
		$groups = array(
			'coins'  => array(),
			'usdt'   => array(),
			'usdc'   => array(),
			'dai'    => array(),
			'tokens' => array(),
		);

		foreach ( self::all() as $id => $coin ) {
			if ( 0 === strpos( $id, 'USDT_' ) ) {
				$groups['usdt'][ $id ] = $coin;
			} elseif ( 0 === strpos( $id, 'USDC_' ) ) {
				$groups['usdc'][ $id ] = $coin;
			} elseif ( 'DAI' === $id || 0 === strpos( $id, 'DAI_' ) ) {
				$groups['dai'][ $id ] = $coin;
			} elseif ( 'native' === $coin['type'] || 0 === strpos( $id, 'ETH_' ) ) {
				$groups['coins'][ $id ] = $coin;
			} else {
				$groups['tokens'][ $id ] = $coin;
			}
		}

		return $groups;
	}

	/**
	 * Format an amount for a coin with correct decimals (trimmed).
	 *
	 * @param float  $amount Amount.
	 * @param string $coin_id Coin ID.
	 * @return string
	 */
	public static function format_amount( $amount, $coin_id ) {
		$coin     = self::get( $coin_id );
		$decimals = $coin ? (int) $coin['decimals'] : 8;
		// Cap display/matching precision for practical payment amounts.
		$decimals = min( $decimals, 8 );
		$formatted = number_format( (float) $amount, $decimals, '.', '' );
		$formatted = rtrim( rtrim( $formatted, '0' ), '.' );
		return $formatted !== '' ? $formatted : '0';
	}

	/**
	 * Build a payment URI / QR payload for wallet apps.
	 *
	 * Standards used:
	 * - BIP-21: bitcoin: / litecoin: / dogecoin:
	 * - EIP-681: ethereum:address@chainId?value=… and token /transfer
	 * - Solana Pay: solana:…?amount=&spl-token=
	 * - TRON / Ripple / Stellar / Monero common URI forms
	 * Falls back to bare address when no reliable URI exists (widest wallet support).
	 *
	 * @param string $coin_id Coin ID.
	 * @param string $address Wallet address.
	 * @param string $amount  Crypto amount (human / UI units).
	 * @return string
	 */
	public static function payment_uri( $coin_id, $address, $amount ) {
		$coin    = self::get( $coin_id );
		$address = trim( (string) $address );
		$amount  = trim( (string) $amount );

		if ( ! $coin || '' === $address ) {
			return $address;
		}

		$type     = $coin['type'];
		$scheme   = $coin['uri_scheme'];
		$verifier = $coin['verifier'];
		$chain_id = self::eip155_chain_id( $coin );

		// EVM tokens (EIP-681 transfer) — chain ID required for non-mainnet safety.
		if ( in_array( $type, array( 'erc20', 'bep20' ), true ) && ! empty( $coin['contract'] ) && $chain_id > 0 ) {
			$base = self::to_base_units( $amount, (int) $coin['decimals'] );
			return sprintf(
				'ethereum:%s@%d/transfer?address=%s&uint256=%s',
				strtolower( $coin['contract'] ),
				$chain_id,
				strtolower( $address ),
				$base
			);
		}

		// Native EVM coins (EIP-681 value in wei / base units — plain integer for max wallet support).
		if ( 'native' === $type && $chain_id > 0 && empty( $coin['contract'] ) ) {
			$wei = self::to_base_units( $amount, (int) $coin['decimals'] );
			return sprintf(
				'ethereum:%s@%d?value=%s',
				strtolower( $address ),
				$chain_id,
				$wei
			);
		}

		// BIP-21 family (full scheme names — wallets reject btc:/ltc:/doge:).
		if ( 'btc' === $verifier || 'btc' === $scheme ) {
			return self::bip21_uri( 'bitcoin', $address, $amount );
		}
		if ( 'ltc' === $verifier || 'ltc' === $scheme ) {
			return self::bip21_uri( 'litecoin', $address, $amount );
		}
		if ( 'doge' === $verifier || 'doge' === $scheme ) {
			return self::bip21_uri( 'dogecoin', $address, $amount );
		}
		if ( 'dash' === $verifier || 'dash' === $scheme ) {
			return self::bip21_uri( 'dash', $address, $amount );
		}
		if ( 'zec' === $verifier || 'zec' === $scheme ) {
			return self::bip21_uri( 'zcash', $address, $amount );
		}
		if ( 'xec' === $verifier || 'xec' === $scheme ) {
			return self::bip21_uri( 'ecash', $address, $amount );
		}
		if ( 'bch' === $verifier || 'bch' === $scheme ) {
			// CashAddr often includes bitcoincash: already — avoid double scheme.
			if ( 0 === stripos( $address, 'bitcoincash:' ) ) {
				$amount = self::normalize_decimal_amount( $amount );
				return ( '' === $amount || '0' === $amount ) ? $address : $address . '?amount=' . $amount;
			}
			return self::bip21_uri( 'bitcoincash', $address, $amount );
		}

		// Solana Pay.
		if ( in_array( $verifier, array( 'sol', 'solana' ), true ) || 'sol' === $scheme ) {
			if ( 'spl' === $type && ! empty( $coin['contract'] ) ) {
				return sprintf(
					'solana:%s?amount=%s&spl-token=%s',
					$address,
					rawurlencode( self::normalize_decimal_amount( $amount ) ),
					rawurlencode( $coin['contract'] )
				);
			}
			return sprintf(
				'solana:%s?amount=%s',
				$address,
				rawurlencode( self::normalize_decimal_amount( $amount ) )
			);
		}

		// TRON — native amount URI; TRC-20 uses bare address (widest TronLink compatibility).
		if ( in_array( $verifier, array( 'trx', 'tron' ), true ) || 'trx' === $scheme ) {
			if ( 'trc20' === $type ) {
				return $address;
			}
			return sprintf( 'tron:%s?amount=%s', $address, rawurlencode( self::normalize_decimal_amount( $amount ) ) );
		}

		// XRP.
		if ( 'xrp' === $verifier || 'xrp' === $scheme ) {
			return sprintf( 'ripple:%s?amount=%s', $address, rawurlencode( self::normalize_decimal_amount( $amount ) ) );
		}

		// Stellar.
		if ( 'xlm' === $verifier || 'xlm' === $scheme ) {
			return sprintf(
				'web+stellar:pay?destination=%s&amount=%s',
				rawurlencode( $address ),
				rawurlencode( self::normalize_decimal_amount( $amount ) )
			);
		}

		// Monero.
		if ( 'xmr' === $verifier || 'xmr' === $scheme ) {
			return sprintf( 'monero:%s?tx_amount=%s', $address, rawurlencode( self::normalize_decimal_amount( $amount ) ) );
		}

		// Cosmos.
		if ( 'atom' === $verifier || 'atom' === $scheme ) {
			return sprintf( 'cosmos:%s?amount=%s', $address, rawurlencode( self::normalize_decimal_amount( $amount ) ) );
		}

		// Bare address for remaining chains (ALGO, NEAR, DOT, FIL, HBAR, EGLD, ZIL, EOS, …).
		return $address;
	}

	/**
	 * BIP-21 style URI.
	 *
	 * @param string $scheme  Full scheme (bitcoin|litecoin|dogecoin).
	 * @param string $address Address (not URL-encoded).
	 * @param string $amount  Decimal amount in whole coins.
	 * @return string
	 */
	private static function bip21_uri( $scheme, $address, $amount ) {
		$amount = self::normalize_decimal_amount( $amount );
		if ( '' === $amount || '0' === $amount ) {
			return $scheme . ':' . $address;
		}
		return $scheme . ':' . $address . '?amount=' . $amount;
	}

	/**
	 * EIP-155 chain ID for a coin.
	 *
	 * @param array<string, mixed> $coin Coin.
	 * @return int
	 */
	private static function eip155_chain_id( array $coin ) {
		$map = array(
			'ethereum'            => 1,
			'optimistic-ethereum' => 10,
			'cronos'              => 25,
			'binance-smart-chain' => 56,
			'ethereum-classic'    => 61,
			'polygon-pos'         => 137,
			'fantom'              => 250,
			'base'                => 8453,
			'arbitrum-one'        => 42161,
			'avalanche'           => 43114,
		);

		$platform = isset( $coin['platform'] ) ? $coin['platform'] : '';
		if ( isset( $map[ $platform ] ) ) {
			return $map[ $platform ];
		}

		$verifier = isset( $coin['verifier'] ) ? $coin['verifier'] : '';
		$by_ver   = array(
			'eth'      => 1,
			'bsc'      => 56,
			'bnb'      => 56,
			'matic'    => 137,
			'avax'     => 43114,
			'ftm'      => 250,
			'cro'      => 25,
			'etc'      => 61,
			'base'     => 8453,
			'arbitrum' => 42161,
			'optimism' => 10,
		);
		return isset( $by_ver[ $verifier ] ) ? $by_ver[ $verifier ] : 0;
	}

	/**
	 * Normalize decimal amount (period separator, no commas).
	 *
	 * @param string $amount Amount.
	 * @return string
	 */
	private static function normalize_decimal_amount( $amount ) {
		$amount = str_replace( ',', '', (string) $amount );
		$amount = trim( $amount );
		if ( ! is_numeric( $amount ) ) {
			return '';
		}
		if ( false !== strpos( $amount, '.' ) ) {
			$amount = rtrim( rtrim( $amount, '0' ), '.' );
		}
		return '' === $amount ? '0' : $amount;
	}

	/**
	 * Convert a decimal amount string to integer base units.
	 *
	 * @param string $amount   Amount.
	 * @param int    $decimals Decimals.
	 * @return string
	 */
	public static function to_base_units( $amount, $decimals ) {
		$amount   = (string) $amount;
		$decimals = max( 0, (int) $decimals );
		if ( false === strpos( $amount, '.' ) ) {
			$whole = preg_replace( '/\D/', '', $amount );
			$whole = ( null === $whole || '' === $whole ) ? '0' : $whole;
			return $whole . str_repeat( '0', $decimals );
		}
		list( $whole, $frac ) = explode( '.', $amount, 2 );
		$frac                 = substr( str_pad( preg_replace( '/\D/', '', $frac ), $decimals, '0' ), 0, $decimals );
		$whole                = preg_replace( '/\D/', '', $whole );
		$whole                = ( null === $whole || '' === $whole ) ? '0' : $whole;
		$combined             = ltrim( $whole . $frac, '0' );
		return '' === $combined ? '0' : $combined;
	}

	/**
	 * Whether auto on-chain verification is supported for this coin.
	 *
	 * @param string $coin_id Coin ID.
	 * @return bool
	 */
	public static function supports_auto_verify( $coin_id ) {
		$coin = self::get( $coin_id );
		if ( ! $coin ) {
			return false;
		}
		$supported = array(
			'btc', 'bch', 'ltc', 'doge', 'dash', 'zec', 'xec', 'eth', 'ethereum', 'arbitrum', 'optimism', 'base', 'bsc', 'matic', 'avax', 'ftm', 'cro', 'etc',
			'sol', 'solana', 'trx', 'tron', 'xrp', 'xlm',
			'algo', 'hbar', 'near', 'atom', 'egld', 'fil', 'eos', 'dot', 'zil',
		);
		return in_array( $coin['verifier'], $supported, true );
	}
}
