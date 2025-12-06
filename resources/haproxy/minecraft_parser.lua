-- Minecraft Protocol Parser for HAProxy
-- Extracts hostname from Minecraft handshake packet for routing
-- 
-- Packet Structure (with length prefix):
-- [Packet Length: VarInt] [Packet ID: VarInt] [Protocol Version: VarInt] [Server Address: String] [Server Port: Unsigned Short] [Next State: VarInt]
--
-- The Server Address field contains the hostname the client used to connect

-- Helper function to extract hostname from Minecraft packet
local function extractHostnameFromPacket(data)
    if not data or type(data) ~= "string" or #data == 0 then
        return nil
    end
    
    -- Need at least 5 bytes for a valid handshake packet
    if #data < 5 then
        return nil
    end
    
    -- Minecraft packets have a length prefix (VarInt) before the packet ID
    -- Structure: [Packet Length: VarInt] [Packet ID: VarInt] [Protocol Version: VarInt] [Server Address: String] ...
    local offset = 1
    
    -- Read packet length (VarInt) - skip this, we don't need it
    local packetLength, offset = readVarInt(data, offset)
    if not packetLength then
        return nil
    end
    
    -- Read VarInt (packet ID) - should be 0x00 for Handshake
    local packetId, offset = readVarInt(data, offset)
    if not packetId or packetId ~= 0 then
        -- Not a handshake packet
        return nil
    end
    
    -- Read VarInt (protocol version) - skip this
    local protocolVersion, offset = readVarInt(data, offset)
    if not protocolVersion then
        return nil
    end
    
    -- Read String (server address) - THIS IS THE HOSTNAME
    local hostname, offset = readString(data, offset)
    
    if hostname and hostname ~= "" then
        -- Trim any whitespace and return the hostname
        hostname = hostname:match("^%s*(.-)%s*$")  -- Trim leading/trailing whitespace
        if hostname and hostname ~= "" then
            return hostname
        end
    end
    
    return nil
end

-- Register a fetch function to extract hostname
core.register_fetches("minecraft_hostname", function(txn)
    -- Get the request buffer (TCP payload)
    -- For TCP mode with inspect-delay, we can access raw TCP data
    if not txn.req then
        return nil
    end
    
    -- Get the request data as a string
    -- In TCP mode, this contains the raw packet bytes
    -- Note: This fetch is called during ACL evaluation after inspect-delay
    local data = nil
    
    -- Try to get the data in TCP mode
    -- In TCP mode, we use dup() to get a copy of the request buffer
    -- IMPORTANT: dup() creates a copy without consuming the original data
    -- The original data will be forwarded to the backend automatically
    local ok, result = pcall(function()
        -- Check if channel can receive data
        if not txn.req:may_recv() then
            return nil
        end
        -- Use dup() to duplicate the request buffer (works in TCP mode)
        -- This creates a copy of the data without consuming it
        -- The original data remains in the buffer for forwarding
        local dupData = txn.req:dup()
        -- Check if we got valid data (not empty string or nil)
        if dupData and type(dupData) == "string" and #dupData > 0 then
            return dupData
        end
        return nil
    end)
    
    if not ok or not result then
        -- If dup() fails or returns nil/empty, data not available yet
        return nil
    end
    
    data = result
    
    -- Extract hostname using helper function
    -- The original data is still in the buffer and will be forwarded
    return extractHostnameFromPacket(data)
end)

-- Also register as an action to set a variable (alternative approach)
core.register_action("extract_minecraft_hostname", { "tcp-req" }, function(txn)
    if not txn.req then
        return
    end
    
    -- Check if channel can receive data (data should be available after inspect-delay)
    if not txn.req:may_recv() then
        -- Channel is closed, no data available
        return
    end
    
    local ok, data = pcall(function()
        -- Try to get data - dup() should work after inspect-delay
        -- IMPORTANT: dup() creates a copy without consuming the original data
        -- The original data will be forwarded to the backend automatically
        local dupData = txn.req:dup()
        -- Check if we got valid data (not empty string or nil)
        if dupData and type(dupData) == "string" and #dupData > 0 then
            return dupData
        end
        return nil
    end)
    
    if ok and data and type(data) == "string" and #data > 0 then
        local hostname = extractHostnameFromPacket(data)
        if hostname then
            -- Set a transaction variable with the hostname
            -- The original packet data is still in the buffer and will be forwarded
            txn:set_var("txn.minecraft_hostname", hostname)
        end
    end
end)

-- Read a VarInt (Variable-length Integer) from the packet
function readVarInt(data, offset)
    offset = offset or 1
    local value = 0
    local position = 0
    local maxPosition = 5 -- VarInt can be at most 5 bytes
    
    while position < maxPosition do
        if offset > data:len() then
            return nil, offset -- Unexpected end of data
        end
        
        local currentByte = string.byte(data, offset)
        value = value | ((currentByte & 0x7F) << (7 * position))
        
        offset = offset + 1
        
        if (currentByte & 0x80) == 0 then
            -- No continuation bit, we're done
            return value, offset
        end
        
        position = position + 1
    end
    
    -- VarInt too long
    return nil, offset
end

-- Read a String from the packet
-- Minecraft strings are length-prefixed with VarInt, then UTF-8 encoded bytes
function readString(data, offset)
    offset = offset or 1
    
    -- Read length (VarInt)
    local length, offset = readVarInt(data, offset)
    if not length or length < 0 or length > 32767 then
        -- Invalid string length
        return nil, offset
    end
    
    -- Check if we have enough bytes
    if offset + length - 1 > data:len() then
        -- Not enough data for the string
        return nil, offset
    end
    
    -- Read the string bytes
    local stringBytes = string.sub(data, offset, offset + length - 1)
    offset = offset + length
    
    return stringBytes, offset
end
