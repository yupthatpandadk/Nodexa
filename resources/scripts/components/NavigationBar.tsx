import * as React from 'react';
import { useEffect, useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt, faPalette } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';

const Bar = styled.div`
    ${tw`w-full sticky top-0 z-50`};
    background: #0a0f19;
    border-bottom: 1px solid #202b3a;
    backdrop-filter: blur(20px); box-shadow: 0 6px 24px rgba(0,0,0,.14);
`;
const RightNavigation = styled.div`
    & > a, & > button, & > .navigation-link {
        ${tw`flex items-center justify-center no-underline text-neutral-300 cursor-pointer transition-all duration-150 rounded-lg`};
        width:38px; height:38px; min-width:38px; margin-left:4px;
        background: #111927;
        border: 1px solid #263244;
        box-shadow: 0 3px 10px rgba(0,0,0,.16);
        &:hover, &.active { color: #fff; background: rgba(99,102,241,.18); border-color: rgba(99,102,241,.35); }
    }
`;
const BrandMark = styled.span`
    display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;
    border-radius:9px; margin-right:9px; font-weight:800; color:white;
    background: linear-gradient(135deg,var(--nodexa-user-accent,#4f8cff),color-mix(in srgb,var(--nodexa-user-accent,#4f8cff) 65%,white)); box-shadow:0 8px 25px rgba(99,102,241,.25);
`;

const ThemeMenu = styled.div`
    position:absolute; right:0; top:46px; width:210px; padding:12px; border-radius:12px;
    background:#0d1420; border:1px solid #263244; box-shadow:0 18px 45px rgba(0,0,0,.38);
`;
const Swatch = styled.button<{ $color: string }>`
    width:28px!important; height:28px!important; min-width:28px; margin:0!important; border-radius:8px!important;
    background:${({ $color }) => $color}!important; border:2px solid rgba(255,255,255,.12)!important;
    &:hover { transform:scale(1.08); border-color:white!important; }
`;

export default () => {
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const [themeOpen, setThemeOpen] = useState(false);
    const [themeMode, setThemeMode] = useState(() => localStorage.getItem('nodexa:theme') || 'midnight');
    const [accent, setAccent] = useState(() => localStorage.getItem('nodexa:accent') || '#4f8cff');
    const accents = ['#4f8cff', '#7c5cff', '#14b8a6', '#22c55e', '#f59e0b', '#ef476f'];
    useEffect(() => {
        document.documentElement.style.setProperty('--nodexa-user-accent', accent);
        localStorage.setItem('nodexa:accent', accent);
    }, [accent]);
    useEffect(() => {
        const themes: Record<string, Record<string, string>> = {
            midnight: { bg:'#07101d', surface:'#0b1423', elevated:'#101b2d', border:'#1d2b40', tint:'#0a2238' },
            ocean: { bg:'#061923', surface:'#082431', elevated:'#0b3040', border:'#14506a', tint:'#07354a' },
            obsidian: { bg:'#090b10', surface:'#101319', elevated:'#171b22', border:'#2b313b', tint:'#151922' },
            aurora: { bg:'#071914', surface:'#0b241d', elevated:'#102e26', border:'#1d5142', tint:'#0b352b' },
            carbon: { bg:'#121416', surface:'#191c1f', elevated:'#22262a', border:'#353b40', tint:'#252a2f' },
            crimson: { bg:'#19090d', surface:'#241015', elevated:'#30151c', border:'#5a2632', tint:'#3a1019' },
        };
        const theme = themes[themeMode] || themes.midnight;
        Object.entries(theme).forEach(([key, value]) => document.documentElement.style.setProperty('--nodexa-' + key, value));
        document.documentElement.setAttribute('data-nodexa-theme', themeMode);
        document.body.style.background = theme.bg;
        localStorage.setItem('nodexa:theme', themeMode);
    }, [themeMode]);
    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };
    return (
        <Bar>
            <SpinnerOverlay visible={isLoggingOut} />
            <div className={'mx-auto w-full flex items-center h-16 sm:h-16 max-w-[1280px] px-4 sm:px-6 gap-3'}>
                <div id={'logo'} className={'flex-1'}>
                    <Link to={'/'} className={'inline-flex items-center text-base sm:text-lg font-header font-semibold tracking-tight no-underline text-white'}>
                        <BrandMark>N</BrandMark><span className={'leading-tight'}>Nodexa<small className={'hidden sm:block text-neutral-500 uppercase tracking-widest'} style={{fontSize:'8px'}}>Game Server Cloud</small></span>
                    </Link>
                </div>
                <RightNavigation className={'flex h-full items-center justify-end flex-nowrap gap-0.5 sm:gap-1'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={'Dashboard'}><NavLink to={'/'} exact><FontAwesomeIcon icon={faLayerGroup} /></NavLink></Tooltip>
                    {rootAdmin && <Tooltip placement={'bottom'} content={'Admin'}><a href={'/admin'} rel={'noreferrer'}><FontAwesomeIcon icon={faCogs} /></a></Tooltip>}
                    <div className={'relative flex items-center navigation-link'}>
                        <Tooltip placement={'bottom'} content={'Tema'}><button onClick={() => setThemeOpen((v) => !v)}><FontAwesomeIcon icon={faPalette} /></button></Tooltip>
                        {themeOpen && <ThemeMenu>
                            <div className={'text-xs font-semibold text-white mb-2'}>Tema</div>
                            <div className={'grid grid-cols-2 gap-1 mb-3'}>
                                {['midnight','ocean','obsidian','aurora','carbon','crimson'].map((mode) => <button key={mode} onClick={() => setThemeMode(mode)} className={'text-xs px-2 py-2 rounded'} style={{width:'auto',height:'auto',background:themeMode===mode?'var(--nodexa-user-accent)':'#182235'}}>{mode === 'midnight' ? 'Midnight' : mode === 'ocean' ? 'Ocean' : mode === 'obsidian' ? 'Obsidian' : mode === 'aurora' ? 'Aurora' : mode === 'carbon' ? 'Carbon' : 'Crimson'}</button>)}
                            </div>
                            <div className={'text-xs font-semibold text-white mb-2'}>Accentfarve</div>
                            <div className={'text-xs text-neutral-500 mb-3'}>Gemmes automatisk på denne enhed.</div>
                            <div className={'flex items-center justify-between'}>{accents.map((color) => <Swatch key={color} $color={color} onClick={() => setAccent(color)} aria-label={'Vælg accentfarve'} />)}</div>
                        </ThemeMenu>}
                    </div>
                    <Tooltip placement={'bottom'} content={'Account Settings'}><NavLink to={'/account'}><span className={'flex items-center w-5 h-5'}><Avatar.User /></span></NavLink></Tooltip>
                    <Tooltip placement={'bottom'} content={'Sign Out'}><button onClick={onTriggerLogout}><FontAwesomeIcon icon={faSignOutAlt} /></button></Tooltip>
                </RightNavigation>
            </div>
        </Bar>
    );
};
