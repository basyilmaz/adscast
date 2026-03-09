import packageJson from "../../package.json";

const appVersion = process.env.NEXT_PUBLIC_APP_VERSION?.trim();
const appVendor = process.env.NEXT_PUBLIC_APP_VENDOR?.trim();
const appName = process.env.NEXT_PUBLIC_APP_NAME?.trim();

export const APP_INFO = {
  name: appName && appName.length > 0 ? appName : "AdsCast",
  version: appVersion && appVersion.length > 0 ? appVersion : packageJson.version,
  vendor: appVendor && appVendor.length > 0 ? appVendor : "Castintech",
} as const;
