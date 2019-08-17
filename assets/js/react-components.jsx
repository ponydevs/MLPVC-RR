(function() {
  'use strict';

  class SplitSelector extends React.Component {
    static propTypes = {
      linkedIds: PropTypes.arrayOf(PropTypes.number),
      endpoint: PropTypes.string,
      formId: PropTypes.string,
      valueKey: PropTypes.string,
      displayKey: PropTypes.string,
      findGroup: PropTypes.func,
      onSuccess: PropTypes.func,
    };

    constructor(props) {
      super(props);

      this.state = {
        linkedIds: new Set(this.props.linkedIds),
        query: '',
      };

      this.linkedSelect = React.createRef();
      this.unlinkedSelect = React.createRef();
      this.searchInput = React.createRef();

      this.handleLink = this.handleLink.bind(this);
      this.handleUnlink = this.handleUnlink.bind(this);
      this.handleSubmit = this.handleSubmit.bind(this);
      this.handleSearch = this.handleSearch.bind(this);
      this.handleClearSearch = this.handleClearSearch.bind(this);
    }

    handleLink(e) {
      e.preventDefault();

      this.handleSelection(this.unlinkedSelect, 'add');
    }

    handleUnlink(e) {
      e.preventDefault();

      this.handleSelection(this.linkedSelect, 'delete');
    }

    handleSelection(ref, setMethod) {
      const newState = { linkedIds: new Set(this.state.linkedIds) };
      const $select = $(ref.current);
      const $selectedOptions = $select.find(':selected');
      $selectedOptions.prop('selected', false).each((_, el) => {
        newState.linkedIds[setMethod](Number(el.value));
      });
      this.setState(newState);
    }

    handleSubmit(e) {
      e.preventDefault();
      const ids = Array.from(this.state.linkedIds).join(',');

      $.Dialog.wait(false, 'Saving changes');

      $.API.put(this.props.endpoint, { ids }, data => {
        if (!data.status) return $.Dialog.fail(false, data.message);

        this.props.onSuccess(data);
      });
    }

    handleSearch(e) {
      this.setQuery(e.target.value.trim());
    }

    handleClearSearch(e) {
      e.preventDefault();

      this.searchInput.current.value = '';
      this.setQuery('');
    }

    setQuery(query) {
      this.setState({ ...this.state, query });
    }

    render() {
      const { linkedIds, query } = this.state;
      const { formId, groups, valueKey, displayKey, entries } = this.props;

      const entriesByGroup = Object.keys(groups).reduce((a, c) => ({ ...a, [c]: [] }), {});
      entries.forEach(entry => {
        const group = this.props.findGroup(entry);
        entriesByGroup[group].push(entry);
      });

      const linkedGroups = [];
      const unlinkedGroups = [];
      const searching = query !== '';

      const elToOption = el => <option key={el[valueKey]} value={el[valueKey]}>{el[displayKey]}</option>;
      const placeholderOptionIfEmpty = array => array.length > 0 ? array.map(elToOption) :
        <option disabled>(none)</option>;

      $.each(groups, (group, label) => {
        const linkedEntries = [];
        const unlinkedEntries = [];
        entriesByGroup[group].forEach(entry => {
          if (searching && entry[displayKey].toLowerCase().indexOf(query.toLowerCase()) === -1)
            return;
          if (linkedIds.has(entry.id))
            linkedEntries.push(entry);
          else unlinkedEntries.push(entry);
        });
        linkedGroups.push(<optgroup key={group} label={label}>
          {placeholderOptionIfEmpty(linkedEntries)}
        </optgroup>);
        unlinkedGroups.push(<optgroup key={group} label={label}>
          {placeholderOptionIfEmpty(unlinkedEntries)}
        </optgroup>);
      });

      return (<form id={formId} onSubmit={this.handleSubmit}>
        <div className="split-select-wrap">
          <div className="filter-input">
            <input
              ref={this.searchInput}
              type="text"
              placeholder="Search"
              onChange={this.handleSearch}
              spellCheck={false}/>
            <button className="typcn typcn-times red"
              onClick={this.handleClearSearch}
              disabled={query === ''}>
              Clear
            </button>
          </div>
          <div className="split-select">
            <span>Linked</span>
            <select ref={this.linkedSelect} name="listed" multiple={true}>
              {linkedGroups}
            </select>
          </div>
          <div className="buttons">
            <button className="typcn typcn-chevron-left green" title="Link selected" onClick={this.handleLink}/>
            <button className="typcn typcn-chevron-right red" title="Unlink selected" onClick={this.handleUnlink}/>
          </div>
          <div className="split-select">
            <span>Available</span>
            <select ref={this.unlinkedSelect} multiple={true}>
              {unlinkedGroups}
            </select>
          </div>
        </div>
      </form>);
    }
  }

  window.reactComponents = {
    SplitSelector,
  };
})();
