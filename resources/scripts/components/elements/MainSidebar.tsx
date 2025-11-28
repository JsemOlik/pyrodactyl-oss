import styled from 'styled-components';

const MainSidebar = styled.nav`
  width: 300px;
  display: flex;            /* make the sidebar a flex column */
  flex-direction: column;
  flex-shrink: 0;
  border-radius: 8px;
  overflow-x: hidden;
  padding: 32px;
  margin-right: 8px;
  user-select: none;
  background: rgba(0, 0, 0, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);

  & > .pyro-subnav-routes-wrapper {
    display: flex;          /* column list */
    flex-direction: column;
    font-size: 14px;
    min-height: 0;          /* avoid flex overflow issues */

    & > a,
    & > div {
      display: flex;
      position: relative;
      padding: 16px 0;
      gap: 8px;
      font-weight: 600;
      min-height: 56px;
      -webkit-tap-highlight-color: transparent;
      user-select: none;
      user-drag: none;
      -ms-user-drag: none;
      -moz-user-drag: none;
      -webkit-user-drag: none;
      transition: 200ms all ease-in-out;

      &.active {
        color: #fa4e49;
        fill: #fa4e49;
      }
    }

    /* this spacer will consume remaining space and push following items down */
    & > .pyro-subnav-spacer {
      flex: 1 1 auto;
    }
  }
`;

export default MainSidebar;
