//tokenstream.cpp
#include "token8stream.h"
#include "token8.h"
#include "parameter.h"
#include "pcre8.h"

#include <ostream>

const char* Token8Stream::PHP_NAME = "Pun\\Token8Stream";

Token8Stream::Token8Stream()
{
	_flagLF = false;
	_tokenLine = 0;

	_eolId = 0;
	_eosId = 0;
	_unknownId = 0;

}
Token8Stream::~Token8Stream()
{
}

void Token8Stream::checkLineFeed(Token8* token)
{
	if (_input._myChar == 13)
	{
		// skip and expect a line feed
		_input._index += 1;
		_input.fn_peekChar();
	}
	if (_input._myChar == 10) {
		_flagLF = true;
		token->_id = _eolId;
		token->_value.clear();
		token->_line = _tokenLine;
		token->_isSingle = true;
		return;
	}
	throw Php::Exception(pun::invalidCharacter(_input._myChar));
}

unsigned char    
Token8Stream::fn_peekByte() const
{
	if (_input._mystr.size() > _input._index)
	{
		return (unsigned char) _input._mystr[_input._index];
	}
	throw Php::Exception("PeekByte past end of string");
}

unsigned int  Token8Stream::fn_size() const
{
	return _input._mystr.size();
}

const char*      
Token8Stream::fn_data() const
{
	return _input._mystr.data();
}

void Token8Stream::fn_addOffset(unsigned int offset)
{
	_input._index += offset;
}

unsigned char   
Token8Stream::fn_movePeekByte()
{
	_input._index++;
	return fn_peekByte();
}



void Token8Stream::fn_peekToken(Token8* token) {
	auto nextCt = _input.fn_peekChar();
	if (nextCt==0) {
		token->_id = _eosId;
		token->_value.clear();
		token->_line = _tokenLine;
		token->_isSingle = true;
	}
	else if (_input._myChar < 20) {
		this->checkLineFeed(token);
	}
	else {
		token->_line = _tokenLine;
		const char* ccptr = _input._mystr.data();
		token->_value.assign(ccptr + _input._index, nextCt);
		token->_isSingle = false;
		if (nextCt == 1) {
			auto cmap = _singles.get();
			auto fit = cmap->getV(_input._myChar);
			if (fit) {
				token->_id = fit;
				token->_isSingle = true;
			}
			else {
				token->_id = _unknownId;
			}
		}
		else {
			token->_id = _unknownId;
		}
	}
}
// Argument is Token8 to put next value into, return same token object
Php::Value Token8Stream::peekToken(Php::Parameters& params)
{
	Token8* token = pun::check_Token8(params,0);
	fn_peekToken(token);
	return Php::Value(Php::Object(Token8::PHP_NAME, token));
}

void Token8Stream::fn_acceptToken(Token8* token)
{
	if (_flagLF) {
		_flagLF = false;
		_tokenLine += 1;
	}
	_token = std::move(*token);
	_token._line = _tokenLine;
	if (_token._id == _eosId) {
		return;
	}
	else if (_token._id == _eolId) {
		_input._index++;
		return;
	}
	_input._index += _token._value.size();
}

void Token8Stream::acceptToken(Php::Parameters& params)
{
	Token8* token = pun::check_Token8(params,0);
	fn_acceptToken(token);
}

int  Token8Stream::fn_moveNextId() {
	if (_flagLF) {
		_flagLF = false;
		_tokenLine += 1;
		_token._line = _tokenLine;
	}
	auto nextCt = _input.fn_peekChar();
	//Php::out << "peekChar " << nextCt << std::endl;
	if (nextCt==0) {
		_token._id = _eosId;
		_token._value.clear();
		_token._isSingle = true;
	}
	else if (_input._myChar < 20) {
		this->checkLineFeed(&_token);
		_input._index++;
	}
	else {
		//Php::out << std::endl << "Peek: " << _input._myChar <<  " size " << nextCt << std::endl;
		auto matchId = _input.fn_firstMatch(_caps);
		if (_caps._slist.size() > 1) {
			_token._id = _caps._rcode;
			_token._value = _caps._slist[1];
			_token._isSingle = false;
			auto advance = _caps._slist[0].size();

			/* Php::out << std::endl << "0: " << _caps._slist[0] << std::endl;
			Php::out << "1: " << _caps._slist[1] << std::endl;
			Php::out << "size " << advance << std::endl;
			Php::out.flush(); */
			_input._index += advance;
		}
		else {			// no capture, 
			if (matchId != 0) {
				throw Php::Exception("Match Id without 2 captures");
			}
			const char* ccptr = _input._mystr.data();
			_token._value.assign(ccptr + _input._index, nextCt);
			_input._index += nextCt;

			_token._isSingle = false;
			if (nextCt == 1) {
				auto cmap = _singles.get();

				auto fit = cmap->getV(_input._myChar);
				if (fit) {
					_token._id = fit;
					_token._isSingle = true;
				}
				else {
					_token._id = _unknownId;
				}
			}
			else {
				_token._id = _unknownId;
			}
		}
	}
	_token._line = _tokenLine;
	return _token._id;	
}

Php::Value 
Token8Stream::moveNextId()
{
	return Php::Value(fn_moveNextId());
}


Php::Value Token8Stream::moveRegex(Php::Parameters& params)
{
	Pcre8* p8 = pun::check_Pcre8(params,0);
    if (p8 == nullptr) {
        throw Php::Exception("Need Argument of (IdRex8)");
    }
    auto  sp = p8->getImp();
    bool result = false;
    auto code = _input.matchSP(sp, _caps);
    if (code != 0 && _caps._slist.size() > 1) {
    	_token._value = _caps._slist[1];
    	auto advance = _caps._slist[0].size();
    	_input._index += advance;
    	_token._id = _unknownId;
    	_token._isSingle = false;
    	result = true;
    }
    return Php::Value(result);
}

bool Token8Stream::fn_moveRegId(int id)
{
	auto map = _input._remap.get();
    Pcre8_share sp;
    if (!map->getRex(id, sp)) {
        throw Php::Exception("No PCRE2 expression found at index");
    }
    bool result = false;
    auto code = _input.matchSP(sp, _caps);
    if (code != 0 && _caps._slist.size() > 1) {
    	_token._value = _caps._slist[1];
    	auto advance = _caps._slist[0].size();
    	_input._index += advance;
    	_token._id = _unknownId;
    	_token._isSingle = false;
    	result = true;
    }	
    return result;
}

Php::Value Token8Stream::moveRegId(Php::Parameters& params)
{
	int id = pun::check_Int(params,0);
    return Php::Value(this->fn_moveRegId(id));
}

void Token8Stream::setEOSId(Php::Parameters& params)
{
	int id = pun::check_Int(params,0);
	_eosId = id;
}

void Token8Stream::setEOLId(Php::Parameters& params)
{
	int id = pun::check_Int(params,0);
	_eolId = id;	
}

void Token8Stream::setUnknownId(Php::Parameters& params)
{
	int id = pun::check_Int(params,0);
	_unknownId = id;	
}

void Token8Stream::setExpSet(Php::Parameters& params)
{
	_input.setIdList(params);
}

Php::Value Token8Stream::getExpSet()
{
	Php::Value result;
	_input.fn_copyIdList(result);
	return result;
}
// argument is associative array, of string => id
// convert to char32_t -> id
void Token8Stream::setSingles(Php::Parameters& params)
{
	bool isArray = pun::option_Array(params,0);

	if (!isArray) {
		throw Php::Exception(pun::missingParameter("Array(string => int)", 0));
	}
	const Php::Value& v = params[0];

	if (!_singles) {
		_singles = std::make_shared<CharMap>();
	}
	auto cmap = _singles.get();

	for( auto &iter : v) {
		const Php::Value& sval = iter.first;
		const char* cp = sval;
		auto cpsize = sval.size();
		if (cpsize > 0) {
			char32_t code = cp[0];
			int id = iter.second;
			//Php::out << "Cmap setKV " << cp << " "<< code << " id " << id << std::endl;
			cmap->setKV(code,id);
		}
	}
}

Php::Value 
Token8Stream::getOffset() const {
    return Php::Value(int(_input._index));
}

Php::Value  Token8Stream::beforeEOL()  {
	return Php::Value(fn_beforeChar(10));
}

void Token8Stream::setRe8map(Php::Parameters& params)
{
	_input.setRe8map(params);
}

void Token8Stream::setString(std::string &&m)
{
	_input.fn_setString(m);
	_flagLF = true;
}
void Token8Stream::setString(const char* ptr, unsigned int len)
{
	_input.fn_setString(ptr,len);
	_flagLF = true;
}

void Token8Stream::setInput(Php::Parameters& params)
{
	_input.setString(params);
	_flagLF = true;
}

Php::Value Token8Stream::hasPendingTokens() const
{
	bool result = (_token._id != _eosId);
	return Php::Value(result);
}


std::string& 
Token8Stream::fn_getValue()
{
	return _token._value;
}

void Token8Stream::fn_restoreValue(std::string &&m)
{
	_token._value = std::move(m);
}

void Token8Stream::fn_setSingles(CharMap_sp& sp)
{
	_singles = sp;
}

unsigned int Token8Stream::fn_getOffset() const
{
	return _input._index;
}

Token8*  
Token8Stream::fn_getToken(Token8 &token)
{
	token = _token;
	return &token;
}

Php::Value Token8Stream::getToken(Php::Parameters& params) const
{
	Token8* token = pun::check_Token8(params,0);
	*token = _token;
	return Php::Value(Php::Object(Token8::PHP_NAME, token));
}

Php::Value Token8Stream::getLine() const
{
	return Php::Value((int) _tokenLine);
}

Php::Value Token8Stream::getValue() const
{
	return Php::Value(_token._value);
}

Php::Value Token8Stream::getId() const
{
	return Php::Value(_token._id);
}

void Token8Stream::setExpSet(const IdList& list) {
	_input._idlist = list;
}

std::string  Token8Stream::fn_beforeChar(char32_t c) const
{
	return _input.fn_beforeChar(c);
}