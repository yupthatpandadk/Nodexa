import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt } from '@fortawesome/free-solid-svg-icons';
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
    background: #0c1321;
    border-bottom: 1px solid #22304a;
    backdrop-filter: blur(20px); box-shadow: 0 6px 24px rgba(0,0,0,.14);
`;
const RightNavigation = styled.div`
    & > a, & > button, & > .navigation-link {
        ${tw`flex items-center h-9 w-9 justify-center no-underline text-neutral-300 cursor-pointer transition-all duration-150 rounded-lg mx-1`};
        background: #151f31;
        border: 1px solid #22304a;
        &:hover, &.active { color: #fff; background: rgba(99,102,241,.18); border-color: rgba(99,102,241,.35); }
    }
`;
const BrandMark = styled.span`
    display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px;
    border-radius:9px; margin-right:9px; font-weight:800; color:white;
    background: linear-gradient(135deg,#7c5cff,#9d7cff); box-shadow:0 8px 25px rgba(99,102,241,.25);
`;

export default () => {
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
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
            <div className={'mx-auto w-full flex items-center h-14 sm:h-16 max-w-[1280px] px-4 sm:px-6'}>
                <div id={'logo'} className={'flex-1'}>
                    <Link to={'/'} className={'inline-flex items-center text-base sm:text-lg font-header font-semibold tracking-tight no-underline text-white'}>
                        <BrandMark>N</BrandMark><span>Nodexa</span>
                    </Link>
                </div>
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={'Dashboard'}><NavLink to={'/'} exact><FontAwesomeIcon icon={faLayerGroup} /></NavLink></Tooltip>
                    {rootAdmin && <Tooltip placement={'bottom'} content={'Admin'}><a href={'/admin'} rel={'noreferrer'}><FontAwesomeIcon icon={faCogs} /></a></Tooltip>}
                    <Tooltip placement={'bottom'} content={'Account Settings'}><NavLink to={'/account'}><span className={'flex items-center w-5 h-5'}><Avatar.User /></span></NavLink></Tooltip>
                    <Tooltip placement={'bottom'} content={'Sign Out'}><button onClick={onTriggerLogout}><FontAwesomeIcon icon={faSignOutAlt} /></button></Tooltip>
                </RightNavigation>
            </div>
        </Bar>
    );
};
