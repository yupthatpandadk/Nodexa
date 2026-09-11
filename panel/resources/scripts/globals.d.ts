declare module '*.jpg';
declare module '*.jpeg';
declare module '*.png';
declare module '*.gif';
declare module '*.webp';
declare module '*.svg';
declare module '*.css';
declare module '*.scss';
declare module '*.sass';
declare module '*.less';

interface ImportMetaEnv {
    readonly MODE?: string;
    readonly DEV?: boolean;
    readonly PROD?: boolean;
    readonly BASE_URL?: string;
    readonly [key: string]: string | boolean | undefined;
}

interface ImportMeta {
    readonly env?: ImportMetaEnv;
}
